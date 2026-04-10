<?php

class ReportController extends ApplicationController
{
    protected function filters()
    {
        return [
            'before' => [
                'mod_only' => ['only' => ['tagUpdates', 'noteUpdates', 'wikiUpdates', 'postUploads', 'votes', 'setDates']],
                'load_report_params' => ['only' => ['tagUpdates', 'noteUpdates', 'wikiUpdates', 'postUploads', 'votes', 'setDates']],
            ],
        ];
    }

    public function tagUpdates()
    {
        $this->users = Report::tag_updates($this->start_date, $this->end_date, $this->limit, $this->level);
        $this->report_action = 'tag_updates';
        $this->report_title = 'Tag Updates';
        $this->change_params = function ($user_id) {
            return ['history#index', 'search' => 'type:post user:' . User::find_name((int) $user_id)];
        };
        $this->set_title($this->report_title);
        $this->render(['action' => 'common']);
    }

    public function noteUpdates()
    {
        $this->users = Report::note_updates($this->start_date, $this->end_date, $this->limit, $this->level);
        $this->report_action = 'note_updates';
        $this->report_title = 'Note Updates';
        $this->change_params = function ($user_id) {
            return ['note#history', 'user_id' => (int) $user_id];
        };
        $this->set_title($this->report_title);
        $this->render(['action' => 'common']);
    }

    public function wikiUpdates()
    {
        $this->users = Report::wiki_updates($this->start_date, $this->end_date, $this->limit, $this->level);
        $this->report_action = 'wiki_updates';
        $this->report_title = 'Wiki Updates';
        $this->change_params = function ($user_id) {
            return ['wiki#recent_changes', 'user_id' => (int) $user_id];
        };
        $this->set_title($this->report_title);
        $this->render(['action' => 'common']);
    }

    public function postUploads()
    {
        $this->users = Report::post_uploads($this->start_date, $this->end_date, $this->limit, $this->level);
        $this->report_action = 'post_uploads';
        $this->report_title = 'Post Uploads';
        $this->change_params = function ($user_id) {
            return ['post#index', 'tags' => 'user:' . User::find_name((int) $user_id)];
        };
        $this->set_title($this->report_title);
        $this->render(['action' => 'common']);
    }

    public function votes()
    {
        $this->users = Report::votes($this->start_date, $this->end_date, $this->limit, $this->level);
        $this->set_title('User Votes');
    }

    public function setDates()
    {
        $target = (string) $this->params()->report;
        if (!$target) {
            $target = (string) $this->params()->to;
        }

        $allowed = ['tag_updates', 'note_updates', 'wiki_updates', 'post_uploads', 'votes'];
        if (!in_array($target, $allowed, true)) {
            $target = 'tag_updates';
        }

        $params = [
            'report#' . $target,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'limit' => $this->limit,
        ];

        if ($this->level !== null) {
            $params['level'] = $this->level;
        }

        $this->redirectTo($params);
    }

    protected function load_report_params()
    {
        $this->start_date = $this->parse_date_param($this->params()->start_date);
        if (!$this->start_date) {
            $this->start_date = date('Y-m-d', strtotime('-3 days'));
        }

        $this->end_date = $this->parse_date_param($this->params()->end_date);
        if (!$this->end_date) {
            $this->end_date = date('Y-m-d', strtotime('+1 day'));
        }

        $this->level = $this->parse_int_param($this->params()->level);
        $this->limit = $this->parse_int_param($this->params()->limit);
        if (!$this->limit) {
            $this->limit = 29;
        }
        $this->limit = max(1, min(100, (int) $this->limit));

        $this->level_options = ['Any' => ''];
        foreach (CONFIG()->user_levels as $name => $value) {
            $this->level_options[$name] = (int) $value;
        }
    }

    protected function parse_date_param($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timestamp = @strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    protected function parse_int_param($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
