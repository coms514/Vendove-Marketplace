<?php

require __DIR__ . '/bootstrap.php';

$userId = require_login();
$user = current_user();

$errors = [];
$openSection = null;

if (is_post()) {
    verify_csrf();
    $section = input_string($_POST, 'section');
    $openSection = $section;

    switch ($section) {
        case 'profile':
            $bio = input_string($_POST, 'bio', 1000);
            $avatar = (string) ($_POST['avatar'] ?? '');

            try {
                $newPicture = $avatar !== '' ? store_data_url_image($avatar, 'profiles') : null;
                if ($newPicture) {
                    db_exec('UPDATE users SET bio = ?, profile_picture = ? WHERE id = ?', [$bio, $newPicture, $userId]);
                    delete_upload($user['profile_picture']);
                } else {
                    db_exec('UPDATE users SET bio = ? WHERE id = ?', [$bio, $userId]);
                }
                flash('success', 'Profile saved.');
                redirect('account.php');
            } catch (UploadException $e) {
                $errors[] = $e->getMessage();
            }
            break;

        case 'sizes':
            db_exec(
                'UPDATE users SET gender = ?, top_size = ?, bottom_size = ?, shoe_size = ? WHERE id = ?',
                [
                    input_choice($_POST, 'gender', PROFILE_GENDERS),
                    input_choice($_POST, 'top_size', PROFILE_CLOTHING_SIZES),
                    input_choice($_POST, 'bottom_size', PROFILE_CLOTHING_SIZES),
                    input_choice($_POST, 'shoe_size', PROFILE_SHOE_SIZES),
                    $userId,
                ],
            );
            flash('success', 'Size preferences saved.');
            redirect('account.php');

        case 'phone':
            $country = array_key_exists($_POST['country_code'] ?? '', PROFILE_COUNTRY_CODES) ? $_POST['country_code'] : '+1';
            $digits = preg_replace('/\D/', '', (string) ($_POST['phone'] ?? ''));

            if ($digits === '') {
                db_exec('UPDATE users SET phone = NULL WHERE id = ?', [$userId]);
                flash('success', 'Phone number removed.');
                redirect('account.php');
            }
            if (strlen($digits) < 7 || strlen($digits) > 12) {
                $errors[] = 'Please enter a valid phone number.';
                break;
            }

            $formatted = strlen($digits) === 10
                ? sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6))
                : $digits;
            db_exec('UPDATE users SET phone = ? WHERE id = ?', [$country . ' ' . $formatted, $userId]);
            flash('success', 'Phone number saved.');
            redirect('account.php');

        case 'address':
            $address = [
                'full_name' => input_string($_POST, 'full_name', 255),
                'street_address' => input_string($_POST, 'street_address', 255),
                'city' => input_string($_POST, 'city', 255),
                'state' => input_string($_POST, 'state', 255),
                'zip_code' => input_string($_POST, 'zip_code', 20),
            ];

            if (in_array('', $address, true)) {
                $errors[] = 'Please fill in every address field.';
                break;
            }

            db_exec(
                'UPDATE users SET full_name = ?, street_address = ?, city = ?, state = ?, zip_code = ? WHERE id = ?',
                [...array_values($address), $userId],
            );
            flash('success', 'Shipping address saved.');
            redirect('account.php');

        default:
            abort(400);
    }

    $user = array_merge($user, array_intersect_key($_POST, $user));
}

$phone = (string) ($user['phone'] ?? '');
$countryCode = '+1';
$phoneNumber = $phone;
if (preg_match('/^(\+\d+)\s(.*)$/', $phone, $matches)) {
    [, $countryCode, $phoneNumber] = $matches;
}

$pageTitle = 'My account';
$pageStyles = ['cropper.min.css'];
$pageScripts = ['cropper.min.js', 'account.js'];
require APP_ROOT . '/templates/header.php';
?>
<section class="container section">
    <div class="page-header">
        <h1>My account</h1>
        <a class="btn btn--ghost" href="public_profile.php?user_id=<?= $userId ?>">View public profile</a>
    </div>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert--error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <div class="account">
        <form method="post" class="card account__profile form" data-loading-form data-avatar-form>
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="profile">
            <input type="hidden" name="avatar" data-avatar-input>

            <div class="avatar-picker">
                <img class="avatar avatar--xl" src="<?= e(avatar_url($user['profile_picture'])) ?>" alt="Your profile picture" data-avatar-preview>
                <label class="btn btn--ghost btn--sm">
                    Change photo
                    <input type="file" accept="image/jpeg,image/png,image/webp" hidden data-avatar-file>
                </label>
            </div>

            <dl class="specs specs--stacked">
                <div><dt>Username</dt><dd><?= e($user['username']) ?></dd></div>
                <div><dt>Email</dt><dd><?= e($user['email']) ?></dd></div>
                <div><dt>Member since</dt><dd><?= e(format_date($user['created_at'], 'F Y')) ?></dd></div>
            </dl>

            <label class="field">
                <span class="field__label">About me</span>
                <textarea name="bio" rows="4" maxlength="1000" placeholder="Tell buyers and sellers a little about yourself"><?= e($user['bio']) ?></textarea>
            </label>

            <button class="btn btn--primary btn--block" type="submit">Save profile</button>
        </form>

        <div class="stack">
            <details class="card disclosure" <?= $openSection === 'sizes' ? 'open' : '' ?>>
                <summary class="disclosure__summary">
                    <span>
                        <strong>Size preferences</strong>
                        <span class="muted"><?= e(implode(' · ', array_filter([$user['gender'], $user['top_size'] ? 'Top ' . $user['top_size'] : '', $user['bottom_size'] ? 'Bottom ' . $user['bottom_size'] : '', $user['shoe_size']])) ?: 'Not set') ?></span>
                    </span>
                    <span class="disclosure__edit">Edit</span>
                </summary>
                <form method="post" class="form" data-loading-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="sizes">
                    <div class="form-row">
                        <?php foreach (['gender' => ['Gender', PROFILE_GENDERS], 'shoe_size' => ['Shoe size', PROFILE_SHOE_SIZES], 'top_size' => ['Top size', PROFILE_CLOTHING_SIZES], 'bottom_size' => ['Bottom size', PROFILE_CLOTHING_SIZES]] as $field => [$label, $choices]): ?>
                            <label class="field">
                                <span class="field__label"><?= e($label) ?></span>
                                <select name="<?= e($field) ?>">
                                    <option value="">Not set</option>
                                    <?php foreach ($choices as $choice): ?>
                                        <option <?= $user[$field] === $choice ? 'selected' : '' ?>><?= e($choice) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn btn--primary" type="submit">Save sizes</button>
                </form>
            </details>

            <details class="card disclosure" <?= $openSection === 'phone' ? 'open' : '' ?>>
                <summary class="disclosure__summary">
                    <span>
                        <strong>Phone number</strong>
                        <span class="muted"><?= e($phone !== '' ? $phone : 'Not set') ?></span>
                    </span>
                    <span class="disclosure__edit">Edit</span>
                </summary>
                <form method="post" class="form" data-loading-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="phone">
                    <div class="form-row">
                        <label class="field">
                            <span class="field__label">Country</span>
                            <select name="country_code">
                                <?php foreach (PROFILE_COUNTRY_CODES as $code => $label): ?>
                                    <option value="<?= e($code) ?>" <?= $countryCode === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span class="field__label">Number</span>
                            <input type="tel" name="phone" value="<?= e($phoneNumber) ?>" placeholder="(555) 555-5555" autocomplete="tel-national" data-phone-input>
                        </label>
                    </div>
                    <button class="btn btn--primary" type="submit">Save phone</button>
                </form>
            </details>

            <details class="card disclosure" <?= $openSection === 'address' ? 'open' : '' ?>>
                <summary class="disclosure__summary">
                    <span>
                        <strong>Shipping address</strong>
                        <span class="muted"><?= e($user['street_address'] ? $user['street_address'] . ', ' . $user['city'] : 'Not set') ?></span>
                    </span>
                    <span class="disclosure__edit">Edit</span>
                </summary>
                <form method="post" class="form" data-loading-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="address">
                    <label class="field">
                        <span class="field__label">Full name</span>
                        <input type="text" name="full_name" value="<?= e($user['full_name']) ?>" autocomplete="name" required>
                    </label>
                    <label class="field">
                        <span class="field__label">Street address</span>
                        <input type="text" name="street_address" value="<?= e($user['street_address']) ?>" autocomplete="street-address" required>
                    </label>
                    <div class="form-row form-row--3">
                        <label class="field">
                            <span class="field__label">City</span>
                            <input type="text" name="city" value="<?= e($user['city']) ?>" autocomplete="address-level2" required>
                        </label>
                        <label class="field">
                            <span class="field__label">State / province</span>
                            <input type="text" name="state" value="<?= e($user['state']) ?>" autocomplete="address-level1" required>
                        </label>
                        <label class="field">
                            <span class="field__label">ZIP / postal code</span>
                            <input type="text" name="zip_code" value="<?= e($user['zip_code']) ?>" autocomplete="postal-code" required>
                        </label>
                    </div>
                    <button class="btn btn--primary" type="submit">Save address</button>
                </form>
            </details>
        </div>
    </div>
</section>

<dialog class="dialog" data-avatar-dialog>
    <div class="dialog__body">
        <h2 class="dialog__title">Adjust your photo</h2>
        <div class="crop-area crop-area--round"><img alt="" data-avatar-crop-image></div>
        <div class="dialog__actions">
            <button type="button" class="btn btn--ghost" data-avatar-cancel>Cancel</button>
            <button type="button" class="btn btn--primary" data-avatar-apply>Use photo</button>
        </div>
    </div>
</dialog>
<?php require APP_ROOT . '/templates/footer.php'; ?>
