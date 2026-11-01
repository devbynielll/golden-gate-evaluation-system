<?php

namespace App\Support;

use DomainException;
use Illuminate\Support\Str;

class PositionalClassValues
{
    // Strict CSV within a cell: quoted literal commas and doubled quotes are supported.
    public static function split(string $value): array
    {
        $values=[]; $part=''; $quoted=false; $closed=false;
        for ($i=0,$length=strlen($value);$i<$length;$i++) {
            $char=$value[$i];
            if ($quoted) {
                if ($char==='"') {
                    if ($i+1<$length && $value[$i+1]==='"') { $part.='"'; $i++; }
                    else { $quoted=false; $closed=true; }
                } else $part.=$char;
            } elseif ($char===',') {
                $values[]=trim($part); $part=''; $closed=false;
            } elseif ($char==='"') {
                if ($closed || trim($part)!=='') throw new DomainException('Ambiguous quotation in comma-separated cell. Quote each complete value and double literal quotes.');
                $part=''; $quoted=true;
            } elseif ($closed && !ctype_space($char)) {
                throw new DomainException('Unexpected text after a quoted value. Separate values with commas.');
            } elseif (!$closed) $part.=$char;
        }
        if ($quoted) throw new DomainException('Unclosed quote in comma-separated cell.');
        $values[]=trim($part);
        return $values;
    }

    public static function labels(string $value, array $expected, string $label): array
    {
        $count=count($expected);
        if ($count===1) {
            // Preserve literal single-subject labels; accept explicit CSV quoting too.
            if ($expected[0] !== null && Str::lower(Str::squish($value)) === Str::lower(Str::squish($expected[0]))) return [$value];
            if (str_starts_with($value,'"') && str_ends_with($value,'"')) {
                $single=self::split($value);
                if (count($single)===1) return $single;
            }
            return [$value];
        }
        if ($value==='') return array_fill(0,$count,'');
        $parts=self::split($value);
        if (count($parts)===$count) return $parts; // Existing per-assignment validation stays authoritative.
        // Unquoted literal commas can be resolved ONLY against the exact ordered
        // labels for the supplied authoritative subject/faculty IDs. Never guess boundaries.
        $normalize=fn($text)=>Str::lower(preg_replace('/\s*,\s*/u',',',Str::squish($text)));
        if (!str_contains($value,'"') && !in_array(null,$expected,true)
            && $normalize($value)===$normalize(implode(',',$expected))) return $expected;
        // Preserve older accepted pipe-formatted files; commas are now the default.
        if (str_contains($value,'|')) {
            $legacy=array_map('trim',explode('|',$value));
            if (count($legacy)===$count) return $legacy;
        }
        throw new DomainException("{$label}: expected {$count} positional values, parsed ".count($parts).'. Check column mapping and matching IDs. For literal commas, quote each whole value (for example: "Art, Culture",Information Management). No assignments from this row were saved.');
    }
}
