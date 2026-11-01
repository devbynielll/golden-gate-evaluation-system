<?php
namespace Tests\Unit;
use App\Support\PositionalClassValues;
use PHPUnit\Framework\TestCase;
class PositionalClassValuesTest extends TestCase
{
 public function test_quoted_literal_commas_and_doubled_quotes_are_preserved(): void
 {
  $this->assertSame(['Art, Culture','PEREZ, "NEIL"'],PositionalClassValues::split('"Art, Culture","PEREZ, ""NEIL"""'));
 }
 public function test_unclosed_quote_is_not_silently_repaired(): void
 {
  $this->expectException(\DomainException::class);PositionalClassValues::split('"Art, Culture,Information Management');
 }
 public function test_unknown_comma_boundaries_are_rejected_instead_of_guessed(): void
 {
  $this->expectException(\DomainException::class);PositionalClassValues::labels('Art, Unknown,Information Management',['Art Appreciation','Information Management'],'Description');
 }
 public function test_single_literal_commas_and_existing_pipe_files_remain_supported(): void
 {
  $this->assertSame(['PEREZ, NEIL'],PositionalClassValues::labels('PEREZ, NEIL',['PEREZ, NEIL'],'Faculty Name'));
  $this->assertSame(['Art Appreciation','Information Management'],PositionalClassValues::labels('Art Appreciation|Information Management',['Art Appreciation','Information Management'],'Description'));
 }
}
