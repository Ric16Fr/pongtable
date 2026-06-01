<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('lets a referee change their own password', function () {
    $referee = User::factory()->referee()->create([
        'password' => Hash::make('old-password'),
    ]);
    $this->actingAs($referee);

    Livewire::test('pages::settings')
        ->set('currentPassword', 'old-password')
        ->set('newPassword', 'new-strong-pw')
        ->set('newPasswordConfirmation', 'new-strong-pw')
        ->call('changePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-strong-pw', $referee->fresh()->password))->toBeTrue();
});

it('lets an admin change their own password', function () {
    $admin = User::factory()->admin()->create([
        'password' => Hash::make('old-password'),
    ]);
    $this->actingAs($admin);

    Livewire::test('pages::settings')
        ->set('currentPassword', 'old-password')
        ->set('newPassword', 'new-strong-pw')
        ->set('newPasswordConfirmation', 'new-strong-pw')
        ->call('changePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-strong-pw', $admin->fresh()->password))->toBeTrue();
});

it('rejects a password change when the current password is wrong', function () {
    $user = User::factory()->referee()->create([
        'password' => Hash::make('right-password'),
    ]);
    $this->actingAs($user);

    Livewire::test('pages::settings')
        ->set('currentPassword', 'wrong-password')
        ->set('newPassword', 'new-strong-pw')
        ->set('newPasswordConfirmation', 'new-strong-pw')
        ->call('changePassword')
        ->assertHasErrors(['currentPassword']);

    expect(Hash::check('right-password', $user->fresh()->password))->toBeTrue();
});

it('rejects a new password that does not match its confirmation', function () {
    $user = User::factory()->admin()->create([
        'password' => Hash::make('old-password'),
    ]);
    $this->actingAs($user);

    Livewire::test('pages::settings')
        ->set('currentPassword', 'old-password')
        ->set('newPassword', 'new-strong-pw')
        ->set('newPasswordConfirmation', 'mismatched')
        ->call('changePassword')
        ->assertHasErrors(['newPassword']);
});

it('rejects a new password that is too short', function () {
    $user = User::factory()->admin()->create([
        'password' => Hash::make('old-password'),
    ]);
    $this->actingAs($user);

    Livewire::test('pages::settings')
        ->set('currentPassword', 'old-password')
        ->set('newPassword', 'short')
        ->set('newPasswordConfirmation', 'short')
        ->call('changePassword')
        ->assertHasErrors(['newPassword']);
});

it('lets an admin create a new referee', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test('pages::settings')
        ->set('newRefereeName', 'ref-new')
        ->set('newRefereePassword', 'strong-pw-123')
        ->call('createReferee')
        ->assertHasNoErrors()
        ->assertSet('newRefereeName', '')
        ->assertSet('newRefereePassword', '');

    $created = User::where('name', 'ref-new')->first();
    expect($created)->not->toBeNull()
        ->and($created->role)->toBe('referee')
        ->and(Hash::check('strong-pw-123', $created->password))->toBeTrue();
});

it('rejects creating a referee with an existing name', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->referee()->create(['name' => 'ref1']);
    $this->actingAs($admin);

    Livewire::test('pages::settings')
        ->set('newRefereeName', 'ref1')
        ->set('newRefereePassword', 'strong-pw-123')
        ->call('createReferee')
        ->assertHasErrors(['newRefereeName']);
});

it('forbids a referee from creating other users', function () {
    $referee = User::factory()->referee()->create();
    $this->actingAs($referee);

    Livewire::test('pages::settings')
        ->set('newRefereeName', 'ref-new')
        ->set('newRefereePassword', 'strong-pw-123')
        ->call('createReferee')
        ->assertStatus(403);

    expect(User::where('name', 'ref-new')->exists())->toBeFalse();
});

it('lets an admin reset a referee password', function () {
    $admin = User::factory()->admin()->create();
    $referee = User::factory()->referee()->create([
        'password' => Hash::make('old-password'),
    ]);
    $this->actingAs($admin);

    Livewire::test('pages::settings')
        ->call('startReset', $referee->id)
        ->assertSet('resetUserId', $referee->id)
        ->set('resetPassword', 'fresh-pw-9999')
        ->call('resetUserPassword')
        ->assertHasNoErrors()
        ->assertSet('resetUserId', null);

    expect(Hash::check('fresh-pw-9999', $referee->fresh()->password))->toBeTrue();
});

it('refuses to start a password reset for an admin account', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test('pages::settings')
        ->call('startReset', $otherAdmin->id)
        ->assertStatus(422);
});

it('lets an admin delete a referee', function () {
    $admin = User::factory()->admin()->create();
    $referee = User::factory()->referee()->create();
    $this->actingAs($admin);

    Livewire::test('pages::settings')
        ->call('deleteReferee', $referee->id)
        ->assertHasNoErrors();

    expect(User::find($referee->id))->toBeNull();
});

it('refuses to delete an admin account', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test('pages::settings')
        ->call('deleteReferee', $otherAdmin->id)
        ->assertStatus(422);

    expect(User::find($otherAdmin->id))->not->toBeNull();
});

it('forbids a referee from resetting other users', function () {
    $referee = User::factory()->referee()->create();
    $other = User::factory()->referee()->create();
    $this->actingAs($referee);

    Livewire::test('pages::settings')
        ->call('startReset', $other->id)
        ->assertStatus(403);
});

it('forbids a referee from deleting other users', function () {
    $referee = User::factory()->referee()->create();
    $other = User::factory()->referee()->create();
    $this->actingAs($referee);

    Livewire::test('pages::settings')
        ->call('deleteReferee', $other->id)
        ->assertStatus(403);

    expect(User::find($other->id))->not->toBeNull();
});
