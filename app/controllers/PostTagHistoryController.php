<?php
class PostTagHistoryController extends ApplicationController
{
    public function index()
    {
        $options = [];
        if (!empty($this->params()->post_id)) {
            $options['post_id'] = (int) $this->params()->post_id;
        }
        if (!empty($this->params()->user_id)) {
            $options['user_id'] = (int) $this->params()->user_id;
        }
        if (!empty($this->params()->user_name)) {
            $options['user_name'] = (string) $this->params()->user_name;
        }

        $this->histories = PostTagHistory::generate_sql($options)
            ->order('post_tag_histories.id DESC')
            ->paginate($this->page_number(), 50);

        $this->respondTo([
            'html' => function () {
                $search_parts = [];
                if (!empty($this->params()->post_id)) {
                    $search_parts[] = 'post:' . (int) $this->params()->post_id;
                }
                if (!empty($this->params()->user_name)) {
                    $search_parts[] = 'user:' . (string) $this->params()->user_name;
                }
                $this->redirectTo(['history#index', 'search' => implode(' ', $search_parts)]);
            },
            'json' => function () {
                $this->render(['json' => $this->histories->toJson()]);
            },
            'xml' => function () {
                $this->render(['xml' => $this->histories, 'root' => 'histories']);
            }
        ]);
    }
}
