<?php // The account hint stays identical on setup, login and password changes. ?>
<label for="password-account"><?= t('password_account_label') ?></label>
<input id="password-account" type="text" name="username" autocomplete="username" value="<?= htmlspecialchars(mikanBoxPasswordAccount(), ENT_QUOTES, 'UTF-8') ?>" readonly>
<p class="login-hint"><?= t('password_account_hint') ?></p>
