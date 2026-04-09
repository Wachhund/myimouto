<div id="terms-of-service">
    <h2>Terms of Service</h2>
    <p>Version: <?= $this->h($this->tos_version) ?></p>

    <div class="tos-content">
        <p>Please review our terms of service. By using this site, you agree to the following terms.</p>
        <!-- Operator customizes this content -->
    </div>

    <?php if ($this->needs_acceptance) : ?>
        <form method="post" action="<?= $this->urlFor(['tos#accept']) ?>">
            <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token, ['id' => '']) ?>
            <?= $this->hiddenFieldTag('return_to', $this->return_to) ?>
            <p>
                <label>
                    <input type="checkbox" name="accept" value="1" required>
                    I have read and accept the Terms of Service (Version <?= $this->h($this->tos_version) ?>)
                </label>
            </p>
            <?= $this->submitTag('Accept Terms') ?>
        </form>
    <?php endif ?>
</div>
