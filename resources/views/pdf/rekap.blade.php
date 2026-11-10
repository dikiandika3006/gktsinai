@extends('pdf.layout')
@section('title', 'Rekap Wilayah x Status')
@section('subtitle', 'Rekapitulasi jemaat per wilayah dan status, per '.now()->format('d/m/Y'))
@section('content')
    @include('pdf.partials.matrix')
@endsection
