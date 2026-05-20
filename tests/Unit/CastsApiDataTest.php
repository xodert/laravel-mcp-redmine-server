<?php

declare(strict_types=1);

use App\Mcp\Concerns\CastsApiData;

// Anonymous class to expose the trait methods for testing
$subject = new class
{
    use CastsApiData;

    public function str(mixed $v, string $d = ''): string
    {
        return $this->strOf($v, $d);
    }

    public function int(mixed $v, int $d = 0): int
    {
        return $this->intOf($v, $d);
    }

    public function float(mixed $v, float $d = 0.0): float
    {
        return $this->floatOf($v, $d);
    }
};

describe('strOf', function () use ($subject): void {
    it('casts string scalar', fn () => expect($subject->str('hello'))->toBe('hello'));
    it('casts int scalar to string', fn () => expect($subject->str(42))->toBe('42'));
    it('casts float scalar to string', fn () => expect($subject->str(3.14))->toBe('3.14'));
    it('casts bool true to string', fn () => expect($subject->str(true))->toBe('1'));
    it('returns default for null', fn () => expect($subject->str(null, 'fallback'))->toBe('fallback'));
    it('returns default for array', fn () => expect($subject->str([], 'fallback'))->toBe('fallback'));
    it('returns default for object', fn () => expect($subject->str(new stdClass, 'fallback'))->toBe('fallback'));
    it('uses empty string as default', fn () => expect($subject->str(null))->toBe(''));
});

describe('intOf', function () use ($subject): void {
    it('casts int scalar', fn () => expect($subject->int(42))->toBe(42));
    it('casts numeric string', fn () => expect($subject->int('7'))->toBe(7));
    it('casts float to int', fn () => expect($subject->int(3.9))->toBe(3));
    it('returns default for null', fn () => expect($subject->int(null, 99))->toBe(99));
    it('returns default for array', fn () => expect($subject->int([], 99))->toBe(99));
    it('uses 0 as default', fn () => expect($subject->int(null))->toBe(0));
});

describe('floatOf', function () use ($subject): void {
    it('casts float scalar', fn () => expect($subject->float(1.5))->toBe(1.5));
    it('casts int to float', fn () => expect($subject->float(2))->toBe(2.0));
    it('casts numeric string', fn () => expect($subject->float('3.14'))->toEqual(3.14));
    it('returns default for null', fn () => expect($subject->float(null, 9.9))->toBe(9.9));
    it('returns default for array', fn () => expect($subject->float([], 1.0))->toBe(1.0));
    it('uses 0.0 as default', fn () => expect($subject->float(null))->toBe(0.0));
});
