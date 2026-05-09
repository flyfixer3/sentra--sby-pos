<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CurrencyNormalizationTest extends TestCase
{
    /**
     * @dataProvider currencyProvider
     */
    public function test_it_normalizes_rupiah_currency_inputs($input, int $expected): void
    {
        $this->assertSame($expected, normalize_currency($input));
    }

    public function currencyProvider(): array
    {
        return [
            'integer' => [400000, 400000],
            'numeric string' => ['400000', 400000],
            'dot thousands' => ['400.000', 400000],
            'rupiah prefix' => ['Rp 400.000', 400000],
            'multiple dot thousands' => ['1.250.000', 1250000],
            'comma thousands' => ['1,250,000', 1250000],
            'empty string' => ['', 0],
            'null' => [null, 0],
        ];
    }
}
