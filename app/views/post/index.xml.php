<?php

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo $this->contentTag('posts', function () {
    // Make it look pretty.
    echo "\n";

    foreach ($this->posts as $post) {
        echo '  ' . $this->tag('post', $post->api_attributes(), false, true) . "\n";
    }
}, ['count' => $this->posts->totalRows(), 'offset' => ($this->posts->currentPage() - 1) * $this->posts->perPage()]);
