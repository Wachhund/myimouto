Hello, <?= $this->user->pretty_name() ?>. A password reset has been requested for your account.

Click the following link to set a new password:

   <?= $this->reset_url ?>

This link will expire in 24 hours. If you did not request this reset, you can safely ignore this email.
