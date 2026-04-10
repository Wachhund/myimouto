<?php
foreach (glob(dirname(__FILE__).'/Post/*.php') as $trait) require $trait;

class Post extends Rails\ActiveRecord\Base
{
    use PostSqlMethods, PostCommentMethods, PostImageStoreMethods,
        PostVoteMethods, PostTagMethods, PostCountMethods,
        Post\CacheMethods, PostParentMethods, PostFileMethods,
        PostChangeSequenceMethods, PostRatingMethods, PostStatusMethods,
        PostApiMethods, /*PostMirrorMethods, */PostFrameMethods;
    
    use Moebooru\Versioning\VersioningTrait;
    
    protected $previous_id;
    
    protected $next_id;
    
    public $updater_user_id;
    
    public $updater_ip_addr;
    
    static public function init_versioning($v)
    {
        $v->versioned_attributes([
            'source' => ['default' => ''],
            'cached_tags',
            # MI: Allowing reverting to default.
            'is_shown_in_index' => ['default' => true, 'allow_reverting_to_default' => true],
            'rating',
            'is_rating_locked' => ['default' => false],
            'is_note_locked' => ['default' => false],
            'parent_id' => ['default' => null],
            // iTODO: uncomment when frames are enabled
            // 'frames_pending' => ['default' => '', 'allow_reverting_to_default' => true]
        ]);
    }
    
    public function __call($method, $params)
    {
        switch(true) {
            # Checking status: $paramsost->is_pending();
            case (strpos($method, 'is_') === 0):
                $status = str_replace('is_', '', $method);
                return $this->status == $status;
            default:
                return parent::__call($method, $params);
        }
    }

    public function next_id()
    {
        if ($this->next_id === null) {
            $post = Post::available()->where('id > ?', $this->id)->limit(1)->first();
            $this->next_id = $post ? $post->id : false;
        }
        return $this->next_id;
    }

    public function previous_id()
    {
        if ($this->previous_id === null) {
            $post = Post::available()->where('id < ?', $this->id)->order('id DESC')->limit(1)->first();
            $this->previous_id = $post ? $post->id : false;
        }
        return $this->previous_id;
    }
    
    public function author()
    {
        return $this->user ? $this->user->name : null;
    }
    
    public function can_be_seen_by($user = null, array $options = array())
    {
        if (empty($options['show_deleted']) && $this->status == 'deleted') {
            return false;
        }
        
        return CONFIG()->can_see_post($user, $this);
    }
    
    public function normalized_source()
    {
        if (preg_match('/pixiv\.net\/img/', $this->source)) {
            if (preg_match('/(\d+)(_s|_m|(_big)?_p\d+)?\.\w+(\?\d+)?\z/', $this->source, $m))
                $img_id = $m[1];
            else
                $img_id = null;
            return "http://www.pixiv.net/member_illust.php?mode=medium&illust_id=" . $img_id;
        } elseif (strpos($this->source, 'http://') === 0 || strpos($this->source, 'https://') === 0)
            return $this->source;
        else
            return 'http://' . $this->source;
    }
    
    public function clear_avatars()
    {
        User::clear_avatars($this->id);
    }
    
    public function approve($approver_id)
    {
        $old_status = $this->status;

        # Resolve ALL pending (unresolved) flags
        $unresolved = FlaggedPostDetail::where('post_id = ? AND is_resolved = 0', $this->id)->take();
        if ($unresolved) {
            foreach ($unresolved as $flag) {
                $flag->resolve($approver_id);
            }
        }

        $this->updateAttributes(array('status' => 'active', 'approver_id' => $approver_id));

        # Don't bump posts if the status wasn't "pending"; it might be "flagged".
        if ($old_status == 'pending' and CONFIG()->hide_pending_posts) {
            // $this->touch_index_timestamp();
            $this->save();
        }
    }
    
    public function voted_by($score = null)
    {
        # Cache results
        if (!$this->voted_by) {
            foreach (range(1, 3) as $v) {
                $this->voted_by[$v] =
                    User::where("v.post_id = ? and v.score = ?", $this->id, $v)
                        ->joins("JOIN post_votes v ON v.user_id = users.id")
                        ->select("users.name, users.id")
                        ->order("v.updated_at DESC")
                        ->take()
                        ->getAttributes(['id', 'name']) ?: array();
            }
        }
        
        if (func_num_args())
            return $this->voted_by[$score];
        return $this->voted_by;
    }

    public function can_user_delete(User $user = null)
    {
        if (!$user)
            $user = current_user();
        
        if (!$user->has_permission($this))
            return false;
        elseif (!$user->is_mod_or_higher() && !$this->is_held() && (strtotime(date('Y-m-d H:i:s')) - strtotime($this->created_at)) > 60*60*24)
            return false;
        
        return true;
    }
    
    public function favorited_by()
    {
        return $this->voted_by(3);
    }
    
    public function active_notes()
    {
        return $this->notes ? $this->notes->select(function($x){return $x->is_active;}) : array();
    }
    
    public function latest_flag()
    {
        # Return the most recent unresolved flag, or the most recent flag if all are resolved
        $flag = FlaggedPostDetail::where('post_id = ? AND is_resolved = 0', $this->id)->order('created_at DESC')->first();
        if (!$flag) {
            $flag = FlaggedPostDetail::where('post_id = ?', $this->id)->order('created_at DESC')->first();
        }
        return $flag;
    }

    public function set_flag_detail($reason, $creator_id, $reason_category = null, $parent_post_id = null)
    {
        $attrs = array('post_id' => $this->id, 'reason' => $reason, 'user_id' => $creator_id, 'is_resolved' => false);
        if ($reason_category) {
            $attrs['reason_category'] = $reason_category;
        }
        if ($parent_post_id) {
            $attrs['parent_post_id'] = $parent_post_id;
        }
        FlaggedPostDetail::create($attrs);
    }

    public function flag($reason, $creator_id, $reason_category = null, $parent_post_id = null)
    {
        $this->updateAttribute('status', 'flagged');
        $this->set_flag_detail($reason, $creator_id, $reason_category, $parent_post_id);
    }
    
    public function destroy_with_reason($reason, $current_user)
    {
        // PROJ-46 AC-11: Guard against double-deletion (e621ng #1736).
        if ($this->status === 'deleted') {
            return false;
        }

        // Post.transaction do
        $existing_flag = $this->latest_flag();
        if ($existing_flag && !$existing_flag->is_resolved)
            $existing_flag->resolve($current_user->id);
        $this->flag($reason, $current_user->id);
        $this->first_delete();

        if (CONFIG()->delete_posts_permanently)
            $this->delete_from_database();
        // end
        return true;
    }

    static public function static_destroy_with_reason($id, $reason, $current_user)
    {
        $post = Post::find($id);
        return $post->destroy_with_reason($reason, $current_user);
    }

    public function first_delete()
    {
        $this->runCallbacks('delete', function() {
            $this->updateAttributes(array('status' => 'deleted'));
        });
    }

    public function delete_from_database()
    {
        $this->runCallbacks('destroy', function() {
            $this->delete_file();
            self::connection()->executeSql('UPDATE pools SET post_count = post_count - 1 WHERE id IN (SELECT pool_id FROM pools_posts WHERE post_id = ?)', $this->id);
            self::connection()->executeSql('UPDATE tags SET post_count = post_count - 1 WHERE id IN (SELECT tag_id FROM posts_tags WHERE post_id = ?)', $this->id);
            # MI: Destroying pool posts manually so their histories are deleted by foreign keys.
            # This is done in Pool too. This could be done with a MySQL trigger.
            PoolPost::destroyAll('post_id = ?', $this->id);
            self::connection()->executeSql("DELETE FROM posts WHERE id = ?", $this->id);
        });
    }

    public function replace_file_from_path($file_path, $original_name = null, &$replacement_context = null)
    {
        $file_path = (string)$file_path;
        $replacement_context = [
            'old_paths' => [],
            'new_paths' => []
        ];

        if ($file_path === '' || !is_file($file_path)) {
            $this->errors()->add('file', 'replacement file not found');
            return false;
        }

        $old_md5 = (string)$this->md5;
        $old_paths = $this->replacement_storage_paths();

        $this->tempfile_path = $file_path;
        $this->tempfile_name = $original_name ?: basename($file_path);
        $this->is_import = true;

        if (!$this->ensure_tempfile_exists()) {
            return false;
        }
        if (!$this->determine_content_type()) {
            return false;
        }
        if (!$this->validate_content_type()) {
            return false;
        }

        $this->set_image_dimensions();
        if (!$this->regenerate_hash()) {
            return false;
        }

        if ((string)$this->md5 === $old_md5) {
            $this->errors()->add('md5', 'matches current post file');
            return false;
        }

        if (self::where('md5 = ? AND id <> ?', $this->md5, $this->id)->exists()) {
            $this->errors()->add('md5', 'already exists');
            return false;
        }

        $this->sample_width = null;
        $this->sample_height = null;
        $this->sample_size = null;
        $this->jpeg_width = null;
        $this->jpeg_height = null;
        $this->jpeg_size = null;

        if (!$this->generate_sample(true)) {
            return false;
        }
        if (!$this->generate_jpeg(true)) {
            return false;
        }
        if (!$this->generate_preview(true)) {
            return false;
        }

        try {
            $this->move_file();
        } catch (Exception $e) {
            $this->errors()->add('file', 'replacement move failed: ' . $e->getMessage());
            return false;
        }

        $new_paths = $this->replacement_storage_paths();
        $replacement_context = [
            'old_paths' => $old_paths,
            'new_paths' => $new_paths
        ];

        $actor = function_exists('current_user') ? current_user() : null;
        $update = [
            'md5' => $this->md5,
            'file_ext' => $this->file_ext,
            'file_size' => $this->file_size,
            'width' => $this->width,
            'height' => $this->height,
            'sample_width' => $this->sample_width,
            'sample_height' => $this->sample_height,
            'sample_size' => $this->sample_size,
            'jpeg_width' => $this->jpeg_width,
            'jpeg_height' => $this->jpeg_height,
            'jpeg_size' => $this->jpeg_size,
            'preview_width' => $this->preview_width,
            'preview_height' => $this->preview_height,
            'actual_preview_width' => $this->actual_preview_width,
            'actual_preview_height' => $this->actual_preview_height,
            'updater_user_id' => $actor ? $actor->id : $this->updater_user_id,
            'updater_ip_addr' => ($actor && !empty($actor->ip_addr)) ? $actor->ip_addr : $this->updater_ip_addr
        ];

        if (!$this->updateAttributes($update)) {
            $this->cleanup_staged_replacement_paths($old_paths, $new_paths);
            return false;
        }

        $this->cleanup_replaced_files($old_paths);
        $this->delete_tempfile();
        return true;
    }
    
    public function undelete()
    {
        if ($this->status == 'active') {
            return;
        }
        $this->runCallbacks('undelete', function() {
            $this->updateAttributes(['status' => 'active']);
        });
    }
    
    public function service()
    {
        return CONFIG()->local_image_service;
    }
    
    public function service_icon()
    {
        return "/favicon.ico";
    }
    
    protected function callbacks()
    {
        return [
            'before_create' => ['set_index_timestamp'],
            'after_create'  => ['after_creation'],
            
            'before_delete' => ['clear_avatars'],
            'after_delete'  => ['give_favorites_to_parent', 'decrement_count'],
            
            'after_undelete'=> ['increment_count'],
            
            'before_save'   => ['commit_tags', 'filter_parent_id'],
            'after_save'    => ['update_parent', 'save_post_history', 'expire_cache'],
            
            'after_destroy' => ['expire_cache'],
            
            'before_validation_on_create' => [
                'download_source', 'ensure_tempfile_exists', 'determine_content_type',
                'validate_content_type', 'generate_hash', 'set_image_dimensions',
                'set_image_status', 'check_pending_count', 'generate_sample',
                'generate_jpeg', 'generate_preview', 'move_file'
            ],
            'after_validation_on_create'  => ['before_creation']
        ];
    }
    
    protected function associations()
    {
        return [
            'belongs_to' => [
                'user',
                'approver' => ['class_name' => 'User']
            ],
            'has_many' => [
                'flag_details' => ['class_name' => "FlaggedPostDetail"],
                'notes'       => [function() { $this->order('id DESC')->where('is_active = 1'); }],
                'comments'    => [function() { $this->order("id"); }],
                'children'    => [
                    function() { $this->order('id')->where("status != 'deleted'"); },
                    'class_name' => 'Post',
                    'foreign_key' => 'parent_id'
                ],
                'tag_history' => [function() { $this->order("id DESC"); }, 'class_name' => 'PostTagHistory'],
            ]
        ];
    }
    
    protected function before_creation()
    {
        $this->upload = !empty($_FILES['post']['tmp_name']['file']) ? true : false;
        
        if (CONFIG()->tags_from_filename)
            $this->get_tags_from_filename();
        if (CONFIG()->source_from_filename)
            $this->get_source_from_filename();
        
        if (!$this->rating)
            $this->rating = CONFIG()->default_rating_upload;
        
        $this->rating = strtolower(substr($this->rating, 0, 1));
        
        if ($this->gif() && CONFIG()->add_gif_tag_to_gif)
            $this->new_tags[] = 'gif';
        elseif ($this->flash() && CONFIG()->add_flash_tag_to_swf)
            $this->new_tags[] = 'flash';
        
        if ($this->new_tags)
            $this->old_tags = 'tagme';
        
        $this->cached_tags = 'tagme';
        
        !$this->parent_id && $this->parent_id = null;
        !$this->source && $this->source = null;
        
        $this->random = mt_rand();
        
        Tag::find_or_create_by_name('tagme');
    }
    
    protected function after_creation()
    {
        if ($this->new_tags) {
            $this->clearChangedAttributes();
            $this->commit_tags();
            
            $update = [];
            foreach (array_keys($this->changedAttributes()) as $attrName) {
                $update[$attrName] = $this->getAttribute($attrName);
            }
            $this->updateColumns($update);
        }
    }
    
    protected function set_index_timestamp()
    {
        $this->index_timestamp = date('Y-m-d H:i:s');
    }

    private function replacement_storage_paths()
    {
        return array_unique(array_filter([
            $this->file_path(),
            $this->preview_path(),
            $this->sample_path(),
            $this->jpeg_path()
        ]));
    }

    private function cleanup_replaced_files(array $old_paths)
    {
        $current_paths = $this->replacement_storage_paths();
        foreach ($old_paths as $path) {
            if (in_array($path, $current_paths, true)) {
                continue;
            }
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function cleanup_staged_replacement_paths(array $old_paths, array $new_paths)
    {
        foreach ($new_paths as $path) {
            if (in_array($path, $old_paths, true)) {
                continue;
            }
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
    
    # Added to avoid SQL constraint errors if parent_id passed isn't a valid post.
    protected function filter_parent_id()
    {
        if (($parent_id = trim($this->parent_id)) && Post::where(['id' => $parent_id])->first())
            $this->parent_id = $parent_id;
        else
            $this->parent_id = null;
    }
    
    protected function scopes()
    {
        return [
            'available' => function() {
                $this->where("posts.status <> ?", "deleted");
            },
            'has_any_tags' => function($tags) {
                // where('posts.tags_index @@ ?', Array(tags).map { |t| t.to_escaped_for_tsquery }.join(' | '))
            },
            'has_all_tags' => function($tags) {
                $this
                    ->joins('INNER JOIN posts_tags pti ON p.id = pti.post_id JOIN tags ti ON pti.tag_id = ti.id')
                    ->where('ti.name IN ('.implode(', ', array_fill(0, count($tags), '?')).')');
                // where('posts.tags_index @@ ?', Array(tags).map { |t| t.to_escaped_for_tsquery }.join(' & '))
            },
            'flagged' => function() {
                $this->where("status = ?", "flagged");
            }
        ];
    }
}
