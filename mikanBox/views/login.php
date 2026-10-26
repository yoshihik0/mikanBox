<?php
// Sign-in screen (SQLite edition). Included by admin.php for visitors whose
// browser is not a registered device. Expects: $loginError, $installedVersion,
// $isInitialSetup, $isDemoMode.
$setupState = mikanBoxSetupTokenState();
$hasDevices = mikanBoxDeviceActiveCount() > 0;
$e = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$inputStyle = 'padding: 10px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.9rem;';
$buttonStyle = 'width: 100%; padding: 10px; font-size: 0.95rem; margin-top: 10px;';

$deviceField = static function () use ($e, $inputStyle) {
    ?>
    <input type="text" name="device_name" maxlength="60" required placeholder="<?= $e(t('device_label_name')) ?>" style="<?= $inputStyle ?>">
    <?php
};
// Registration with the setup token: the first device, or recovery when every device is lost.
$setupForm = static function (bool $withDisplayName, string $button) use ($e, $inputStyle, $buttonStyle, $deviceField) {
    ?>
    <form method="post" style="display: flex; flex-direction: column; gap: 10px;">
        <?= csrfField() ?>
        <input type="hidden" name="login_action" value="setup">
        <input type="password" name="setup_token" autocomplete="off" required placeholder="<?= $e(t('device_label_setup_token')) ?>" style="<?= $inputStyle ?>">
        <input type="text" name="username" autocomplete="off" required placeholder="<?= $e(t('label_username')) ?>" style="<?= $inputStyle ?>">
        <?php if ($withDisplayName): ?>
        <input type="text" name="display_name" required placeholder="<?= $e(t('label_display_name')) ?>" style="<?= $inputStyle ?>">
        <?php endif; ?>
        <?php $deviceField(); ?>
        <button type="submit" class="btn btn-blue" style="<?= $buttonStyle ?>"><?= $e($button) ?></button>
    </form>
    <?php
};
// Shown whenever no usable setup token exists and nothing else can register a device.
$guide = static function () use ($e, $setupState) {
    if ($setupState === 'weak') echo '<div class="error">' . $e(t('device_token_weak', MIKANBOX_SETUP_TOKEN_MIN_LENGTH)) . '</div>';
    if ($setupState === 'used') echo '<div class="error">' . $e(t('device_token_used')) . '</div>';
    ?>
    <p><strong><?= $e(t('device_guide_message')) ?></strong>
        <a href="<?= $e(mikanBoxAuthGuideUrl()) ?>" target="_blank" rel="noopener"><?= $e(t('device_guide_link')) ?></a></p>
    <?php
};
?>
<!DOCTYPE html>
<html lang="<?= getSystemLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>🍊mikanBox - <?= t('admin_login') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=block" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=<?= $installedVersion ?>">
</head>
<body class="login-body">
    <div class="login-box">
        <div class="login-title"><span>🍊</span><span>mikanBox</span></div>
        <?php
        if (isset($_SESSION['admin_message'])) {
            echo "<div class='error' style='background-color:#e2f0d9; border-color:#a9d08e; color:#375623; margin-bottom:15px; font-size:0.85rem; padding:10px; border-radius:4px; border:1px solid;'>" . $e($_SESSION['admin_message']) . "</div>";
            unset($_SESSION['admin_message']);
        }
        if ($isDemoMode) echo '<p>' . t('hint_demo_login') . '</p>';
        if (!empty($loginError)) echo '<div class="error" role="alert">' . $e($loginError) . '</div>';
        ?>

        <?php if (!$isInitialSetup): ?>
            <p style="font-size: 0.85rem; line-height: 1.5;"><?= $e(t('device_password_ended')) ?></p>
        <?php endif; ?>

        <?php if ($isInitialSetup): ?>
            <?php if ($setupState === 'ready'): ?>
                <p><strong><?= t('hint_initial_setup') ?></strong><br><?= $e(t('device_setup_intro')) ?></p>
                <?php $setupForm(true, t('device_btn_register')); ?>
            <?php else: ?>
                <?php $guide(); ?>
            <?php endif; ?>
        <?php elseif (!$hasDevices): ?>
            <?php /* Users exist but no device does (an upgraded site): only the setup token can register one. */ ?>
            <?php if ($setupState === 'ready'): ?>
                <p><?= $e(t('device_setup_intro')) ?></p>
                <?php $setupForm(false, t('device_btn_register')); ?>
            <?php else: ?>
                <?php $guide(); ?>
            <?php endif; ?>
        <?php else: ?>
            <p><?= $e(t('device_unregistered_intro')) ?></p>
            <form method="post" style="display: flex; flex-direction: column; gap: 10px;">
                <?= csrfField() ?>
                <input type="hidden" name="login_action" value="claim">
                <input type="text" name="code" autocomplete="off" required autofocus placeholder="XXXXX-XXXXX" style="<?= $inputStyle ?>">
                <?php $deviceField(); ?>
                <button type="submit" class="btn btn-blue" style="<?= $buttonStyle ?>"><?= $e(t('device_btn_register')) ?></button>
            </form>
            <details style="margin-top: 18px;">
                <summary style="cursor: pointer; font-size: 0.85rem;"><?= $e(t('device_recovery_summary')) ?></summary>
                <p style="font-size: 0.82rem; color: var(--text-sub); line-height: 1.4;"><?= $e(t('device_recovery_intro')) ?></p>
                <?php if ($setupState === 'ready'): ?>
                    <?php $setupForm(false, t('device_btn_recover')); ?>
                <?php else: ?>
                    <?php $guide(); ?>
                <?php endif; ?>
            </details>
        <?php endif; ?>

        <?php if ($isDemoMode): ?>
            <p><a href="<?= $e(basename($_SERVER['PHP_SELF'])) ?>"><?= t('btn_demo_back') ?></a></p>
        <?php endif; ?>
    </div>
</body>
</html>
