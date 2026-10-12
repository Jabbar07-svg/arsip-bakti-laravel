<?php

namespace Tests\MariaDB;

use App\Support\ArchiveAccess;
use Illuminate\Support\Facades\DB;

class ArchiveDivisionScopeTest extends MariaDbTestCase
{
    public function test_database_scope_and_record_access_agree_for_normalized_division_values(): void
    {
        $head = SyntheticMariaDbFixtures::user('Kepala Divisi', 'Keuangan');

        $sameDivision = SyntheticMariaDbFixtures::archive($head, ' keuangan ', 'Internal Divisi');
        $otherDivision = SyntheticMariaDbFixtures::archive($head, 'Pengadaan', 'Internal Divisi');
        $withoutDivision = SyntheticMariaDbFixtures::archive($head, null, 'Publik');
        $emptyDivision = SyntheticMariaDbFixtures::archive($head, '', 'Publik');
        $confidential = SyntheticMariaDbFixtures::archive($head, 'KEUANGAN', 'Dokumen Rahasia');

        $visibleIds = ArchiveAccess::scope(DB::table('arsip as a'), $head, 'a')
            ->orderBy('a.id')
            ->pluck('a.id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $this->assertSame([$sameDivision], $visibleIds);

        $sameDivisionRecord = DB::table('arsip')->where('id', $sameDivision)->first();
        $otherDivisionRecord = DB::table('arsip')->where('id', $otherDivision)->first();
        $withoutDivisionRecord = DB::table('arsip')->where('id', $withoutDivision)->first();
        $emptyDivisionRecord = DB::table('arsip')->where('id', $emptyDivision)->first();
        $confidentialRecord = DB::table('arsip')->where('id', $confidential)->first();

        $this->assertTrue(ArchiveAccess::canView($head, $sameDivisionRecord));
        $this->assertFalse(ArchiveAccess::canView($head, $otherDivisionRecord));
        $this->assertFalse(ArchiveAccess::canView($head, $withoutDivisionRecord));
        $this->assertFalse(ArchiveAccess::canView($head, $emptyDivisionRecord));
        $this->assertFalse(ArchiveAccess::canView($head, $confidentialRecord));
    }
}
