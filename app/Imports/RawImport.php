<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

/** Baca isi Excel mentah (semua sheet) untuk pemetaan kolom. */
class RawImport implements Import, WithCalculatedFormulas {}
