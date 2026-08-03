<?php

function qr_code_svg_data_uri(string $text): string
{
    $matrix = qr_code_matrix($text);
    $size = count($matrix);
    $border = 4;
    $canvasSize = $size + ($border * 2);
    $path = '';

    for ($y = 0; $y < $size; $y++) {
        $runStart = null;
        for ($x = 0; $x <= $size; $x++) {
            $dark = $x < $size && !empty($matrix[$y][$x]);
            if ($dark && $runStart === null) {
                $runStart = $x;
            }
            if ((!$dark || $x === $size) && $runStart !== null) {
                $path .= 'M' . ($runStart + $border) . ' ' . ($y + $border)
                    . 'h' . ($x - $runStart) . 'v1h-' . ($x - $runStart) . 'z';
                $runStart = null;
            }
        }
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $canvasSize . ' ' . $canvasSize . '" shape-rendering="crispEdges">'
        . '<rect width="100%" height="100%" fill="#fff"/>'
        . '<path fill="#071422" d="' . $path . '"/>'
        . '</svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function qr_code_matrix(string $text): array
{
    $bytes = array_values(unpack('C*', $text) ?: []);
    [$version, $spec] = qr_code_choose_version(count($bytes));
    $dataCodewords = array_sum(array_map(
        static fn(array $block): int => $block[0] * $block[1],
        $spec['blocks']
    ));
    $charCountBits = $version <= 9 ? 8 : 16;
    $bits = [];
    qr_code_append_bits($bits, 0x4, 4);
    qr_code_append_bits($bits, count($bytes), $charCountBits);
    foreach ($bytes as $byte) {
        qr_code_append_bits($bits, $byte, 8);
    }

    $capacityBits = $dataCodewords * 8;
    qr_code_append_bits($bits, 0, min(4, max(0, $capacityBits - count($bits))));
    while (count($bits) % 8 !== 0) {
        $bits[] = false;
    }

    $data = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $value = 0;
        for ($j = 0; $j < 8; $j++) {
            $value = ($value << 1) | (!empty($bits[$i + $j]) ? 1 : 0);
        }
        $data[] = $value;
    }

    for ($pad = 0xec; count($data) < $dataCodewords; $pad ^= 0xfd) {
        $data[] = $pad;
    }

    $codewords = qr_code_add_error_correction($data, $spec);

    return qr_code_draw_matrix($version, $spec['align'], $codewords);
}

function qr_code_choose_version(int $byteCount): array
{
    foreach (qr_code_specs() as $version => $spec) {
        $dataCodewords = array_sum(array_map(
            static fn(array $block): int => $block[0] * $block[1],
            $spec['blocks']
        ));
        $charCountBits = $version <= 9 ? 8 : 16;
        if (4 + $charCountBits + ($byteCount * 8) <= $dataCodewords * 8) {
            return [$version, $spec];
        }
    }

    throw new RuntimeException('QR payload is too long.');
}

function qr_code_specs(): array
{
    return [
        1 => ['ec' => 7, 'blocks' => [[1, 19]], 'align' => []],
        2 => ['ec' => 10, 'blocks' => [[1, 34]], 'align' => [6, 18]],
        3 => ['ec' => 15, 'blocks' => [[1, 55]], 'align' => [6, 22]],
        4 => ['ec' => 20, 'blocks' => [[1, 80]], 'align' => [6, 26]],
        5 => ['ec' => 26, 'blocks' => [[1, 108]], 'align' => [6, 30]],
        6 => ['ec' => 18, 'blocks' => [[2, 68]], 'align' => [6, 34]],
        7 => ['ec' => 20, 'blocks' => [[2, 78]], 'align' => [6, 22, 38]],
        8 => ['ec' => 24, 'blocks' => [[2, 97]], 'align' => [6, 24, 42]],
        9 => ['ec' => 30, 'blocks' => [[2, 116]], 'align' => [6, 26, 46]],
        10 => ['ec' => 18, 'blocks' => [[2, 68], [2, 69]], 'align' => [6, 28, 50]],
    ];
}

function qr_code_append_bits(array &$bits, int $value, int $length): void
{
    for ($i = $length - 1; $i >= 0; $i--) {
        $bits[] = (($value >> $i) & 1) !== 0;
    }
}

function qr_code_add_error_correction(array $data, array $spec): array
{
    $dataBlocks = [];
    $offset = 0;
    foreach ($spec['blocks'] as [$count, $dataLength]) {
        for ($i = 0; $i < $count; $i++) {
            $blockData = array_slice($data, $offset, $dataLength);
            $offset += $dataLength;
            $dataBlocks[] = [
                'data' => $blockData,
                'ecc' => qr_code_rs_remainder($blockData, (int)$spec['ec']),
            ];
        }
    }

    $result = [];
    $maxDataLength = max(array_map(static fn(array $block): int => count($block['data']), $dataBlocks));
    for ($i = 0; $i < $maxDataLength; $i++) {
        foreach ($dataBlocks as $block) {
            if (array_key_exists($i, $block['data'])) {
                $result[] = $block['data'][$i];
            }
        }
    }

    for ($i = 0; $i < (int)$spec['ec']; $i++) {
        foreach ($dataBlocks as $block) {
            $result[] = $block['ecc'][$i];
        }
    }

    return $result;
}

function qr_code_rs_remainder(array $data, int $degree): array
{
    $generator = qr_code_rs_generator($degree);
    $remainder = array_fill(0, $degree, 0);

    foreach ($data as $byte) {
        $factor = $byte ^ $remainder[0];
        for ($i = 0; $i < $degree - 1; $i++) {
            $remainder[$i] = $remainder[$i + 1] ^ qr_code_gf_multiply($generator[$i + 1], $factor);
        }
        $remainder[$degree - 1] = qr_code_gf_multiply($generator[$degree], $factor);
    }

    return $remainder;
}

function qr_code_rs_generator(int $degree): array
{
    $generator = [1];
    for ($i = 0; $i < $degree; $i++) {
        $next = array_fill(0, count($generator) + 1, 0);
        foreach ($generator as $index => $coefficient) {
            $next[$index] ^= $coefficient;
            $next[$index + 1] ^= qr_code_gf_multiply($coefficient, qr_code_gf_exp($i));
        }
        $generator = $next;
    }

    return $generator;
}

function qr_code_gf_multiply(int $x, int $y): int
{
    if ($x === 0 || $y === 0) {
        return 0;
    }

    [$exp, $log] = qr_code_gf_tables();

    return $exp[$log[$x] + $log[$y]];
}

function qr_code_gf_exp(int $power): int
{
    [$exp] = qr_code_gf_tables();

    return $exp[$power];
}

function qr_code_gf_tables(): array
{
    static $tables = null;
    if ($tables !== null) {
        return $tables;
    }

    $exp = array_fill(0, 512, 0);
    $log = array_fill(0, 256, 0);
    $x = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $x;
        $log[$x] = $i;
        $x <<= 1;
        if (($x & 0x100) !== 0) {
            $x ^= 0x11d;
        }
    }
    for ($i = 255; $i < 512; $i++) {
        $exp[$i] = $exp[$i - 255];
    }

    $tables = [$exp, $log];

    return $tables;
}

function qr_code_draw_matrix(int $version, array $alignmentPositions, array $codewords): array
{
    $size = 17 + 4 * $version;
    $modules = array_fill(0, $size, array_fill(0, $size, false));
    $function = array_fill(0, $size, array_fill(0, $size, false));

    $set = static function (int $x, int $y, bool $dark, bool $isFunction = true) use (&$modules, &$function, $size): void {
        if ($x < 0 || $y < 0 || $x >= $size || $y >= $size) {
            return;
        }
        $modules[$y][$x] = $dark;
        if ($isFunction) {
            $function[$y][$x] = true;
        }
    };

    qr_code_draw_finder($set, 3, 3);
    qr_code_draw_finder($set, $size - 4, 3);
    qr_code_draw_finder($set, 3, $size - 4);

    for ($i = 8; $i < $size - 8; $i++) {
        $set(6, $i, $i % 2 === 0);
        $set($i, 6, $i % 2 === 0);
    }

    foreach ($alignmentPositions as $x) {
        foreach ($alignmentPositions as $y) {
            if ($function[$y][$x]) {
                continue;
            }
            qr_code_draw_alignment($set, (int)$x, (int)$y);
        }
    }

    qr_code_reserve_format_areas($set, $size, $version);

    $dataBits = [];
    foreach ($codewords as $codeword) {
        qr_code_append_bits($dataBits, $codeword, 8);
    }

    $bitIndex = 0;
    $direction = -1;
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) {
            $right = 5;
        }
        for ($vertical = 0; $vertical < $size; $vertical++) {
            $y = $direction === 1 ? $vertical : $size - 1 - $vertical;
            for ($column = 0; $column < 2; $column++) {
                $x = $right - $column;
                if ($function[$y][$x]) {
                    continue;
                }
                $dark = $bitIndex < count($dataBits) ? (bool)$dataBits[$bitIndex] : false;
                $bitIndex++;
                if ((($x + $y) % 2) === 0) {
                    $dark = !$dark;
                }
                $modules[$y][$x] = $dark;
            }
        }
        $direction *= -1;
    }

    qr_code_draw_format_bits($set, $size);
    if ($version >= 7) {
        qr_code_draw_version_bits($set, $size, $version);
    }

    return $modules;
}

function qr_code_draw_finder(callable $set, int $centerX, int $centerY): void
{
    for ($dy = -4; $dy <= 4; $dy++) {
        for ($dx = -4; $dx <= 4; $dx++) {
            $distance = max(abs($dx), abs($dy));
            $dark = $distance !== 2 && $distance !== 4;
            $set($centerX + $dx, $centerY + $dy, $dark);
        }
    }
}

function qr_code_draw_alignment(callable $set, int $centerX, int $centerY): void
{
    for ($dy = -2; $dy <= 2; $dy++) {
        for ($dx = -2; $dx <= 2; $dx++) {
            $set($centerX + $dx, $centerY + $dy, max(abs($dx), abs($dy)) !== 1);
        }
    }
}

function qr_code_reserve_format_areas(callable $set, int $size, int $version): void
{
    for ($i = 0; $i <= 8; $i++) {
        if ($i !== 6) {
            $set(8, $i, false);
            $set($i, 8, false);
        }
    }
    for ($i = 0; $i < 8; $i++) {
        $set($size - 1 - $i, 8, false);
        $set(8, $size - 1 - $i, false);
    }
    $set(8, $size - 8, true);

    if ($version >= 7) {
        for ($i = 0; $i < 6; $i++) {
            for ($j = 0; $j < 3; $j++) {
                $set($size - 11 + $j, $i, false);
                $set($i, $size - 11 + $j, false);
            }
        }
    }
}

function qr_code_draw_format_bits(callable $set, int $size): void
{
    $errorCorrectionLevelBits = 1; // L
    $mask = 0;
    $data = ($errorCorrectionLevelBits << 3) | $mask;
    $remainder = $data << 10;
    for ($i = 14; $i >= 10; $i--) {
        if ((($remainder >> $i) & 1) !== 0) {
            $remainder ^= 0x537 << ($i - 10);
        }
    }
    $bits = (($data << 10) | $remainder) ^ 0x5412;

    for ($i = 0; $i <= 5; $i++) {
        $set(8, $i, (($bits >> $i) & 1) !== 0);
    }
    $set(8, 7, (($bits >> 6) & 1) !== 0);
    $set(8, 8, (($bits >> 7) & 1) !== 0);
    $set(7, 8, (($bits >> 8) & 1) !== 0);
    for ($i = 9; $i < 15; $i++) {
        $set(14 - $i, 8, (($bits >> $i) & 1) !== 0);
    }

    for ($i = 0; $i < 8; $i++) {
        $set($size - 1 - $i, 8, (($bits >> $i) & 1) !== 0);
    }
    for ($i = 8; $i < 15; $i++) {
        $set(8, $size - 15 + $i, (($bits >> $i) & 1) !== 0);
    }
    $set(8, $size - 8, true);
}

function qr_code_draw_version_bits(callable $set, int $size, int $version): void
{
    $remainder = $version << 12;
    for ($i = 17; $i >= 12; $i--) {
        if ((($remainder >> $i) & 1) !== 0) {
            $remainder ^= 0x1f25 << ($i - 12);
        }
    }
    $bits = ($version << 12) | $remainder;

    for ($i = 0; $i < 18; $i++) {
        $dark = (($bits >> $i) & 1) !== 0;
        $a = $size - 11 + ($i % 3);
        $b = intdiv($i, 3);
        $set($a, $b, $dark);
        $set($b, $a, $dark);
    }
}
