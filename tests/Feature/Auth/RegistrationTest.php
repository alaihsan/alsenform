<?php

test('public registration is unavailable', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
    $this->post('/register')->assertNotFound();
});
