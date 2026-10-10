{{-- Any 5xx status without its own view, e.g. 502 or 504. --}}
@extends('errors.layout', ['status' => 500])
