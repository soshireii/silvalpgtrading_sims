<?php

// Public self-registration is intentionally disabled (see routes/auth.php):
// the owner and cashier are seeded, and delivery staff are created by the owner.

test('the registration screen does not exist', function () {
    $this->get('/register')->assertNotFound();
});

test('people cannot register themselves', function () {
    $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(404);

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
});
