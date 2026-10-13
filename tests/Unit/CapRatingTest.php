<?php

namespace Tests\Unit;

use App\Enums\CapRating;
use PHPUnit\Framework\TestCase;

class CapRatingTest extends TestCase
{
    public function test_from_score_maps_to_maritime_cap_grade(): void
    {
        $this->assertSame(CapRating::Cap1, CapRating::fromScore(4.0));
        $this->assertSame(CapRating::Cap1, CapRating::fromScore(3.5));
        $this->assertSame(CapRating::Cap2, CapRating::fromScore(3.49));
        $this->assertSame(CapRating::Cap2, CapRating::fromScore(2.5));
        $this->assertSame(CapRating::Cap3, CapRating::fromScore(2.49));
        $this->assertSame(CapRating::Cap3, CapRating::fromScore(1.5));
        $this->assertSame(CapRating::Cap4, CapRating::fromScore(1.49));
        $this->assertSame(CapRating::Cap4, CapRating::fromScore(0.0));
        $this->assertNull(CapRating::fromScore(null));
    }

    public function test_labels_and_badge_classes(): void
    {
        $this->assertSame('CAP 1', CapRating::Cap1->label());
        $this->assertSame('CAP 4', CapRating::Cap4->label());
        $this->assertSame('Sangat Baik', CapRating::Cap1->description());
        $this->assertSame('green', CapRating::Cap1->color());
        $this->assertSame('red', CapRating::Cap4->color());
        $this->assertStringContainsString('green', CapRating::Cap1->badgeClass());
    }

    public function test_helpers_delegate_to_enum(): void
    {
        $this->assertSame('green', survey_score_color(3.8));
        $this->assertSame('red', survey_score_color(1.0));
        $this->assertNull(survey_score_color(null));
        $this->assertSame(CapRating::Cap2->badgeClass(), survey_score_badge_class(2.8));
    }
}
