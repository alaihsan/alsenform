{{-- Any 4xx status without its own view, e.g. 400, 405, 408 or 413. --}}
@extends('errors.layout', ['status' => 400])
