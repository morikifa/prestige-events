<?php

function qr_blocks(string $level): array
{
    return [
        'M' => [
            1 => [[1, 26, 16]],
            2 => [[1, 44, 28]],
            3 => [[1, 70, 44]],
            4 => [[2, 50, 32]],
            5 => [[2, 67, 43]],
            6 => [[4, 43, 27]],
            7 => [[4, 49, 31]],
            8 => [[2, 60, 38], [2, 61, 39]],
            9 => [[3, 58, 36], [2, 59, 37]],
            10 => [[4, 69, 43], [1, 70, 44]],
        ],
    ][$level];
}

function qr_alignment_centers(int $version): array
{
    $centers = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
        8 => [6, 24, 42],
        9 => [6, 26, 46],
        10 => [6, 28, 50],
    ];
    return $centers[$version];
}

function qr_gf_tables(): array
{
    static $tables = null;
    if ($tables !== null) {
        return $tables;
    }
    $exp = array_fill(0, 512, 0);
    $log = array_fill(0, 256, 0);
    $value = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $value;
        $log[$value] = $i;
        $value <<= 1;
        if ($value & 0x100) {
            $value ^= 0x11D;
        }
    }
    for ($i = 255; $i < 512; $i++) {
        $exp[$i] = $exp[$i - 255];
    }
    $tables = [$exp, $log];
    return $tables;
}

function qr_gf_mul(int $a, int $b): int
{
    if ($a === 0 || $b === 0) {
        return 0;
    }
    [$exp, $log] = qr_gf_tables();
    return $exp[$log[$a] + $log[$b]];
}

function qr_generator(int $degree): array
{
    [$exp] = qr_gf_tables();
    $poly = [1];
    for ($i = 0; $i < $degree; $i++) {
        $next = array_fill(0, count($poly) + 1, 0);
        foreach ($poly as $index => $coefficient) {
            $next[$index] ^= $coefficient;
            $next[$index + 1] ^= qr_gf_mul($coefficient, $exp[$i]);
        }
        $poly = $next;
    }
    return $poly;
}

function qr_ec_codewords(array $data, int $ecLength): array
{
    $poly = qr_generator($ecLength);
    $buffer = array_merge($data, array_fill(0, $ecLength, 0));
    $dataLength = count($data);

    for ($i = 0; $i < $dataLength; $i++) {
        $factor = $buffer[$i];
        if ($factor === 0) {
            continue;
        }
        foreach ($poly as $index => $coefficient) {
            $buffer[$i + $index] ^= qr_gf_mul($coefficient, $factor);
        }
    }

    return array_slice($buffer, $dataLength, $ecLength);
}

function qr_encode_data(string $text, int $version): array
{
    $bytes = array_values(unpack('C*', $text));
    $bits = [];
    $push = static function (int $value, int $length) use (&$bits): void {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    };

    $push(0b0100, 4);
    $push(count($bytes), $version < 10 ? 8 : 16);
    foreach ($bytes as $byte) {
        $push($byte, 8);
    }

    $blocks = qr_blocks('M')[$version];
    $dataCodewords = 0;
    foreach ($blocks as $block) {
        $dataCodewords += $block[0] * $block[2];
    }
    $capacityBits = $dataCodewords * 8;

    $terminator = min(4, $capacityBits - count($bits));
    for ($i = 0; $i < $terminator; $i++) {
        $bits[] = 0;
    }
    while (count($bits) % 8 !== 0) {
        $bits[] = 0;
    }

    $codewords = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $byte = 0;
        for ($j = 0; $j < 8; $j++) {
            $byte = ($byte << 1) | $bits[$i + $j];
        }
        $codewords[] = $byte;
    }

    $pad = [0xEC, 0x11];
    $index = 0;
    while (count($codewords) < $dataCodewords) {
        $codewords[] = $pad[$index % 2];
        $index++;
    }

    return $codewords;
}

function qr_codewords(string $text, int $version): array
{
    $data = qr_encode_data($text, $version);
    $blocks = qr_blocks('M')[$version];

    $dataBlocks = [];
    $ecBlocks = [];
    $offset = 0;
    foreach ($blocks as $block) {
        [$count, $total, $dataLength] = $block;
        $ecLength = $total - $dataLength;
        for ($i = 0; $i < $count; $i++) {
            $chunk = array_slice($data, $offset, $dataLength);
            $offset += $dataLength;
            $dataBlocks[] = $chunk;
            $ecBlocks[] = qr_ec_codewords($chunk, $ecLength);
        }
    }

    $result = [];
    $maxData = max(array_map('count', $dataBlocks));
    for ($i = 0; $i < $maxData; $i++) {
        foreach ($dataBlocks as $block) {
            if (isset($block[$i])) {
                $result[] = $block[$i];
            }
        }
    }
    $maxEc = max(array_map('count', $ecBlocks));
    for ($i = 0; $i < $maxEc; $i++) {
        foreach ($ecBlocks as $block) {
            if (isset($block[$i])) {
                $result[] = $block[$i];
            }
        }
    }

    return $result;
}

function qr_mask_applies(int $mask, int $row, int $col): bool
{
    switch ($mask) {
        case 0:
            return ($row + $col) % 2 === 0;
        case 1:
            return $row % 2 === 0;
        case 2:
            return $col % 3 === 0;
        case 3:
            return ($row + $col) % 3 === 0;
        case 4:
            return ((int) ($row / 2) + (int) ($col / 3)) % 2 === 0;
        case 5:
            return ($row * $col) % 2 + ($row * $col) % 3 === 0;
        case 6:
            return (($row * $col) % 2 + ($row * $col) % 3) % 2 === 0;
        default:
            return (($row + $col) % 2 + ($row * $col) % 3) % 2 === 0;
    }
}

function qr_base_matrix(int $version): array
{
    $size = $version * 4 + 17;
    $matrix = array_fill(0, $size, array_fill(0, $size, 0));
    $reserved = array_fill(0, $size, array_fill(0, $size, false));

    $set = static function (int $row, int $col, bool $dark) use (&$matrix, &$reserved, $size): void {
        if ($row < 0 || $col < 0 || $row >= $size || $col >= $size) {
            return;
        }
        $matrix[$row][$col] = $dark ? 1 : 0;
        $reserved[$row][$col] = true;
    };

    $finder = static function (int $row, int $col) use ($set): void {
        for ($dy = -1; $dy <= 7; $dy++) {
            for ($dx = -1; $dx <= 7; $dx++) {
                $inside = $dy >= 0 && $dy <= 6 && $dx >= 0 && $dx <= 6;
                $ring = ($dy === 0 || $dy === 6 || $dx === 0 || $dx === 6);
                $core = $dy >= 2 && $dy <= 4 && $dx >= 2 && $dx <= 4;
                $set($row + $dy, $col + $dx, $inside && ($ring || $core));
            }
        }
    };

    $finder(0, 0);
    $finder(0, $size - 7);
    $finder($size - 7, 0);

    for ($i = 8; $i < $size - 8; $i++) {
        $set(6, $i, $i % 2 === 0);
        $set($i, 6, $i % 2 === 0);
    }

    $centers = qr_alignment_centers($version);
    $last = count($centers) - 1;
    foreach ($centers as $rowIndex => $rowCenter) {
        foreach ($centers as $colIndex => $colCenter) {
            if (($rowIndex === 0 && $colIndex === 0) || ($rowIndex === 0 && $colIndex === $last) || ($rowIndex === $last && $colIndex === 0)) {
                continue;
            }
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $dark = max(abs($dy), abs($dx)) !== 1;
                    $set($rowCenter + $dy, $colCenter + $dx, $dark);
                }
            }
        }
    }

    for ($i = 0; $i < 9; $i++) {
        if ($i !== 6) {
            $set(8, $i, false);
            $set($i, 8, false);
        }
    }
    for ($i = 0; $i < 8; $i++) {
        $set(8, $size - 1 - $i, false);
    }
    for ($i = 0; $i < 7; $i++) {
        $set($size - 1 - $i, 8, false);
    }
    $set($size - 8, 8, false);

    if ($version >= 7) {
        $remainder = $version;
        for ($i = 0; $i < 12; $i++) {
            $remainder = ($remainder << 1) ^ (($remainder >> 11) * 0x1F25);
        }
        $bits = ($version << 12) | $remainder;
        for ($i = 0; $i < 18; $i++) {
            $bit = (($bits >> $i) & 1) === 1;
            $a = $size - 11 + $i % 3;
            $b = (int) ($i / 3);
            $set($a, $b, $bit);
            $set($b, $a, $bit);
        }
    }

    return [$matrix, $reserved, $size];
}

function qr_place_data(array $matrix, array $reserved, int $size, array $codewords, int $mask): array
{
    $bitIndex = 0;
    $totalBits = count($codewords) * 8;

    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) {
            $right = 5;
        }
        for ($vertical = 0; $vertical < $size; $vertical++) {
            for ($offset = 0; $offset < 2; $offset++) {
                $col = $right - $offset;
                $upward = (($right + 1) & 2) === 0;
                $row = $upward ? $size - 1 - $vertical : $vertical;

                if ($reserved[$row][$col]) {
                    continue;
                }

                $bit = 0;
                if ($bitIndex < $totalBits) {
                    $byte = $codewords[(int) ($bitIndex / 8)];
                    $bit = ($byte >> (7 - ($bitIndex % 8))) & 1;
                    $bitIndex++;
                }
                if (qr_mask_applies($mask, $row, $col)) {
                    $bit ^= 1;
                }
                $matrix[$row][$col] = $bit;
            }
        }
    }

    return $matrix;
}

function qr_draw_format(array $matrix, int $size, int $mask): array
{
    $data = (0 << 3) | $mask;
    $remainder = $data;
    for ($i = 0; $i < 10; $i++) {
        $remainder = ($remainder << 1) ^ (($remainder >> 9) * 0x537);
    }
    $bits = (($data << 10) | $remainder) ^ 0x5412;

    $bit = static fn(int $index): int => ($bits >> $index) & 1;

    for ($i = 0; $i <= 5; $i++) {
        $matrix[$i][8] = $bit($i);
    }
    $matrix[7][8] = $bit(6);
    $matrix[8][8] = $bit(7);
    $matrix[8][7] = $bit(8);
    for ($i = 9; $i < 15; $i++) {
        $matrix[8][14 - $i] = $bit($i);
    }

    for ($i = 0; $i < 8; $i++) {
        $matrix[8][$size - 1 - $i] = $bit($i);
    }
    for ($i = 8; $i < 15; $i++) {
        $matrix[$size - 15 + $i][8] = $bit($i);
    }
    $matrix[$size - 8][8] = 1;

    return $matrix;
}

function qr_penalty(array $matrix, int $size): int
{
    $score = 0;

    for ($row = 0; $row < $size; $row++) {
        for ($col = 0; $col < $size; $col++) {
            $dark = $matrix[$row][$col] === 1;
            if ($col < $size - 4 && $dark === ($matrix[$row][$col + 1] === 1)
                && $dark === ($matrix[$row][$col + 2] === 1)
                && $dark === ($matrix[$row][$col + 3] === 1)
                && $dark === ($matrix[$row][$col + 4] === 1)) {
                $score += 3;
            }
            if ($row < $size - 4 && $dark === ($matrix[$row + 1][$col] === 1)
                && $dark === ($matrix[$row + 2][$col] === 1)
                && $dark === ($matrix[$row + 3][$col] === 1)
                && $dark === ($matrix[$row + 4][$col] === 1)) {
                $score += 3;
            }
            if ($row < $size - 1 && $col < $size - 1) {
                $block = $matrix[$row][$col] + $matrix[$row][$col + 1] + $matrix[$row + 1][$col] + $matrix[$row + 1][$col + 1];
                if ($block === 0 || $block === 4) {
                    $score += 3;
                }
            }
        }
    }

    $dark = 0;
    foreach ($matrix as $row) {
        $dark += array_sum($row);
    }
    $total = $size * $size;
    $percent = (int) (abs($dark * 100 / $total - 50) / 5);
    $score += $percent * 10;

    return $score;
}

function qr_matrix(string $text, ?int $forcedMask = null): array
{
    $length = strlen($text);
    $version = null;
    foreach (qr_blocks('M') as $candidate => $blocks) {
        $dataCodewords = 0;
        foreach ($blocks as $block) {
            $dataCodewords += $block[0] * $block[2];
        }
        $needed = 4 + ($candidate < 10 ? 8 : 16) + $length * 8;
        if ($needed <= $dataCodewords * 8) {
            $version = $candidate;
            break;
        }
    }

    if ($version === null) {
        throw new InvalidArgumentException('Слишком длинный текст для QR-кода.');
    }

    [$matrix, $reserved, $size] = qr_base_matrix($version);
    $codewords = qr_codewords($text, $version);

    if ($forcedMask !== null) {
        $forcedMatrix = qr_place_data($matrix, $reserved, $size, $codewords, $forcedMask);
        return qr_draw_format($forcedMatrix, $size, $forcedMask);
    }

    $best = null;
    $bestScore = null;
    for ($mask = 0; $mask < 8; $mask++) {
        $candidateMatrix = qr_place_data($matrix, $reserved, $size, $codewords, $mask);
        $candidateMatrix = qr_draw_format($candidateMatrix, $size, $mask);
        $score = qr_penalty($candidateMatrix, $size);
        if ($bestScore === null || $score < $bestScore) {
            $bestScore = $score;
            $best = $candidateMatrix;
        }
    }

    return $best;
}

function qr_svg(string $text, int $size = 132): string
{
    try {
        $matrix = qr_matrix($text);
    } catch (Throwable $exception) {
        return '';
    }

    $count = count($matrix);
    $quiet = 4;
    $total = $count + $quiet * 2;
    $parts = [];

    foreach ($matrix as $row => $values) {
        foreach ($values as $col => $value) {
            if ($value === 1) {
                $parts[] = 'M' . ($col + $quiet) . ' ' . ($row + $quiet) . 'h1v1h-1z';
            }
        }
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $total . ' ' . $total . '" role="img" aria-label="QR-код билета">'
        . '<rect width="' . $total . '" height="' . $total . '" fill="#ffffff"/>'
        . '<path d="' . implode('', $parts) . '" fill="#000000"/>'
        . '</svg>';
}
