<?php

test('the website entry point redirects to sign in', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});
