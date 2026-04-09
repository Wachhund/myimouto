<?php
class UserDeletionController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'mod_only' => ['only' => ['confirm', 'execute']]
            ]
        ];
    }

    /**
     * GET: Show impact preview and confirmation form for staff-initiated deletion.
     */
    public function confirm()
    {
        $this->set_title('Delete User');
        $this->user = User::find($this->params()->id);

        if (!$this->user) {
            $this->respond_to_error("User not found", ['user#index'], ['status' => 404]);
            return;
        }

        // Cannot delete users of equal or higher privilege
        if (current_user()->level <= $this->user->level) {
            $this->respond_to_error(
                "Cannot delete user of equal or higher privilege",
                ['user#show', 'id' => $this->user->id]
            );
            return;
        }

        // Cannot delete yourself
        if (current_user()->id == $this->user->id) {
            $this->respond_to_error(
                "Cannot delete yourself",
                ['user#show', 'id' => $this->user->id]
            );
            return;
        }

        $this->impact = \MyImouto\UserDeletion\DeletionService::previewImpact($this->user);
    }

    /**
     * POST: Execute staff-initiated user deletion/anonymization.
     */
    public function execute()
    {
        $this->user = User::find($this->params()->id);

        if (!$this->user) {
            $this->respond_to_error("User not found", ['user#index'], ['status' => 404]);
            return;
        }

        $reason = trim((string)($this->params()->reason ?: ''));
        if ($reason === '') {
            $this->respond_to_error(
                "A reason is required",
                ['user_deletion#confirm', 'id' => $this->user->id]
            );
            return;
        }

        if (!$this->params()->confirm_deletion) {
            $this->respond_to_error(
                "You must confirm the deletion",
                ['user_deletion#confirm', 'id' => $this->user->id]
            );
            return;
        }

        try {
            \MyImouto\UserDeletion\DeletionService::staffDelete(
                $this->user,
                current_user(),
                $reason
            );

            $this->respond_to_success(
                "User has been anonymized",
                ['user#show', 'id' => $this->user->id]
            );
        } catch (\RuntimeException $e) {
            $this->respond_to_error(
                $e->getMessage(),
                ['user_deletion#confirm', 'id' => $this->user->id]
            );
        }
    }
}
