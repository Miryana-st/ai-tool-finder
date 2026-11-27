<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\Tool\SlugFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The format of slugs users give to categories and tags.
 */
class SlugFormatTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function validSlugs(): array
    {
        return [
            'single word' => ['chatbots'],
            'digits only' => ['2024'],
            'words and digits' => ['gpt-4o-mini'],
            'single character' => ['a'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSlugs(): array
    {
        return [
            'empty' => [''],
            'uppercase' => ['Chatbots'],
            'space' => ['image tools'],
            'underscore' => ['image_tools'],
            'leading hyphen' => ['-tools'],
            'trailing hyphen' => ['tools-'],
            'double hyphen' => ['image--tools'],
            'cyrillic' => ['инструменти'],
            'accented latin' => ['café'],
            'trailing newline' => ["tools\n"],
        ];
    }

    #[DataProvider('validSlugs')]
    public function test_valid_slug_matches(string $slug): void
    {
        $this->assertMatchesRegularExpression(SlugFormat::PATTERN, $slug);
    }

    #[DataProvider('invalidSlugs')]
    public function test_invalid_slug_does_not_match(string $slug): void
    {
        $this->assertDoesNotMatchRegularExpression(SlugFormat::PATTERN, $slug);
    }
}
