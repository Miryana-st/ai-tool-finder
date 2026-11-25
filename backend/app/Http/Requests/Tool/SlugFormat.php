<?php

namespace App\Http\Requests\Tool;

/**
 * The format of a slug given by a user for a category or a tag.
 */
final class SlugFormat
{
    /**
     * Lowercase Latin letters and digits, in words joined by single hyphens.
     */
    public const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const string MESSAGE = 'The slug may only contain lowercase Latin letters, digits and single hyphens between words.';
}
