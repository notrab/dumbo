<?php

namespace Dumbo\Tests\Helpers;

use Dumbo\Helpers\DateHelper;
use PHPUnit\Framework\TestCase;
use DateTimeZone;

class DateTest extends TestCase
{
    public function testNow()
    {
        // Test without timezone
        $now = time();
        $helperNow = DateHelper::now();
        $this->assertEqualsWithDelta(
            $now,
            $helperNow,
            1,
            "DateHelper::now() should return current timestamp"
        );

        // Test with timezone
        $timezone = "America/New_York";
        $nowWithTz = (new \DateTime(
            "now",
            new DateTimeZone($timezone)
        ))->getTimestamp();
        $helperNowWithTz = DateHelper::now($timezone);
        $this->assertEqualsWithDelta(
            $nowWithTz,
            $helperNowWithTz,
            1,
            "DateHelper::now() should return correct timestamp for given timezone"
        );
    }

    public function testTimezoneSelect()
    {
        $html = DateHelper::timezoneSelect("custom-class", "Europe/London");

        $this->assertStringContainsString(
            '<select name="timezone" class="custom-class">',
            $html
        );
        $this->assertStringContainsString(
            '<option value="Europe/London" selected>Europe/London</option>',
            $html
        );
        $this->assertStringContainsString(
            '<option value="America/New_York">America/New_York</option>',
            $html
        );
    }

    public function testFormat()
    {
        $time = strtotime("2023-05-15 10:30:00");
        $formatted = DateHelper::format("Y-m-d H:i:s", $time);
        $this->assertEquals("2023-05-15 10:30:00", $formatted);

        $formatted = DateHelper::format("d/m/Y", "now");
        $this->assertEquals(date("d/m/Y"), $formatted);
    }

    public function testGetMonthNames()
    {
        $months = DateHelper::getMonthNames();
        $this->assertCount(12, $months);
        $this->assertEquals("January", $months[1]);
        $this->assertEquals("December", $months[12]);

        $abbreviatedMonths = DateHelper::getMonthNames(true);
        $this->assertCount(12, $abbreviatedMonths);
        $this->assertEquals("Jan", $abbreviatedMonths[1]);
        $this->assertEquals("Dec", $abbreviatedMonths[12]);
    }

    public function testGetDayNames()
    {
        $days = DateHelper::getDayNames();
        $this->assertCount(7, $days);
        $this->assertEquals("Sunday", $days[0]);
        $this->assertEquals("Saturday", $days[6]);

        $abbreviatedDays = DateHelper::getDayNames(true);
        $this->assertCount(7, $abbreviatedDays);
        $this->assertEquals("Sun", $abbreviatedDays[0]);
        $this->assertEquals("Sat", $abbreviatedDays[6]);
    }
}
