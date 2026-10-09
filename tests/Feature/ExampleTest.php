<?php

test('guests are redirected to the login page from the root', function () {
    $this->get('/')->assertRedirect('/login');
});
