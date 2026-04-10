<?php

// ActiveRecord::load_model(array('ArtistUrl', 'WikiPage'));

class Artist extends Rails\ActiveRecord\Base
{
    protected $alias_names = [];

    protected $member_names = [];

    public $updater_ip_addr = [];

    protected $notes;

    protected $urls;

    public function __toString()
    {
        return $this->name;
    }

    protected function associations()
    {
        return [
            'has_many' => [
                'artist_urls' => ['class_name' => 'ArtistUrl'],
            ],
            'belongs_to' => [
                'updater' => ['class_name' => 'User', 'foreign_key' => "updater_id"],
            ],
        ];
    }

    protected function callbacks()
    {
        return [
            'after_save' => ['commit_urls', 'commit_notes', 'commit_aliases', 'commit_members'],
            'before_validation' => ['normalize'],
        ];
    }

    protected function validations()
    {
        return [
            'name' => ['uniqueness' => true],
        ];
    }

    /* UrlMethods { */
    public static function find_all_by_url($url)
    {
        $url = ArtistUrl::normalize($url);
        $artists = new Rails\ActiveRecord\Collection();

        while ($artists->blank() && strlen($url) > 10) {
            $u = str_replace('*', '%', $url) . '%';
            $artists->merge(Artist::where("artists.alias_id IS NULL AND artists_urls.normalized_url LIKE ?", $u)->joins("JOIN artists_urls ON artists_urls.artist_id = artists.id")->order("artists.name")->take());

            # Remove duplicates based on name
            $artists->unique('name');

            $url = dirname($url);
        }

        return $artists->slice(0, 20);
    }

    protected function commit_urls()
    {
        if ($this->urls) {
            $this->artist_urls->each('destroy');
            foreach (array_unique(array_filter(preg_split('/\v/', $this->urls))) as $url) {
                ArtistUrl::create(['url' => $url, 'artist_id' => $this->id]);
            }
        }
    }

    public function urls()
    {
        $urls = [];
        foreach ($this->artist_urls as $x) {
            $urls[] = $x->url;
        }
        return implode("\n", $urls);
    }

    public function setUrls($url)
    {
        $this->urls = $url;
    }

    /* Note Methods */
    protected function wiki_page()
    {
        return WikiPage::find_page($this->name);
    }

    public function notes_locked()
    {
        if ($this->wiki_page()) {
            return !empty($this->wiki_page()->is_locked);
        }
    }

    public function notes()
    {
        if ($this->wiki_page()) {
            return $this->wiki_page()->body;
        } else {
            return '';
        }
    }

    public function setNotes($notes)
    {
        $this->notes = $notes;
    }

    protected function commit_notes()
    {
        if ($this->notes) {
            if (!$this->wiki_page()) {
                WikiPage::create(['title' => $this->name, 'body' => $this->notes, 'ip_addr' => $this->updater_ip_addr, 'user_id' => $this->updater_id]);
            } elseif ($this->wiki_page()->is_locked) {
                $this->errors()->add('notes', "are locked");
            } else {
                $this->wiki_page()->updateAttributes(['body' => $this->notes, 'ip_addr' => $this->updater_ip_addr, 'user_id' => $this->updater_id]);
            }
        }
    }

    /* Alias Methods */
    protected function commit_aliases()
    {
        self::connection()->executeSql("UPDATE artists SET alias_id = NULL WHERE alias_id = ?", $this->id);

        if ($this->alias_names) {
            foreach ($this->alias_names as $name) {
                $a = Artist::where(['name' => $name])->firstOrCreate()->updateAttributes(['alias_id' => $this->id, 'updater_id' => $this->updater_id]);
            }
        }
    }

    public function alias_names()
    {
        return implode(', ', $this->aliases()->getAttributes('name'));
    }

    public function aliases()
    {
        if ($this->isNewRecord()) {
            return new Rails\ActiveRecord\Collection();
        } else {
            return Artist::where("alias_id = ?", $this->id)->order("name")->take();
        }
    }

    public function alias_name()
    {
        $name = '';

        if ($this->alias_id) {
            try {
                $name = Artist::find($this->alias_id)->name;
            } catch (Rails\ActiveRecord\Exception\RecordNotFoundException $e) {
            }
        }

        return $name;
    }

    /* Group Methods */

    protected function commit_members()
    {
        self::connection()->executeSql("UPDATE artists SET group_id = NULL WHERE group_id = ?", $this->id);

        if ($this->member_names) {
            foreach ($this->member_names as $name) {
                $a = Artist::where(['name' => $name])->firstOrCreate();
                $a->updateAttributes(['group_id' => $this->id, 'updater_id' => $this->updater_id]);
            }
        }
    }

    public function group_name()
    {
        if ($this->group_id) {
            $artist = Artist::where('id = ?', $this->group_id)->first();
            return $artist ? $artist->name : '';
        }
    }

    public function members()
    {
        if ($this->isNewRecord()) {
            return new Rails\ActiveRecord\Collection();
        } else {
            return Artist::where("group_id = ?", $this->id)->order("name")->take();
        }
    }

    public function member_names()
    {
        return implode(', ', $this->members()->getAttributes('name'));
    }

    public function setAliasNames($names)
    {
        if ($names = array_filter(explode(',', trim($names)))) {
            foreach ($names as $name) {
                $this->alias_names[] = trim($name);
            }
        }
    }

    public function setAliasName($name)
    {
        if ($name) {
            if ($artist = Artist::where(['name' => $name])->firstOrCreate()) {
                $this->alias_id = $artist->id;
            } else {
                $this->alias_id = null;
            }
        } else {
            $this->alias_id = null;
        }
    }

    public function setMemberNames($names)
    {
        if ($names = array_filter(explode(',', trim($names)))) {
            foreach ($names as $name) {
                $this->member_names[] = trim($name);
            }
        }
    }

    public function setGroupName($name)
    {
        if (!$name) {
            $this->group_id = null;
        } else {
            $this->group_id = Artist::where(['name' => $name])->firstOrCreate()->id;
        }
    }

    /* Api Methods */
    public function api_attributes()
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'alias_id' => $this->alias_id,
            'group_id' => $this->group_id,
            'urls'     => $this->artist_urls->getAttributes('url'),
        ];
    }

    public function toXml(array $options = [])
    {
        $options['root'] = "artist";
        $options['attributes'] = $this->api_attributes();
        return parent::toXml($options);
    }

    public function asJson(array $args = [])
    {
        return $this->api_attributes();
    }

    public function normalize()
    {
        $this->name = str_replace(' ', '_', trim(strtolower($this->name)));
    }

    public static function generate_sql($name)
    {
        if (strpos($name, 'http') === 0) {
            // $conds = array('artists.id IN (?)', self::where(['url' => $name])->pluck('id'));
            // $sql = self::where('true');
            if (!$ids = self::find_all_by_url($name)->getAttributes('id')) {
                $ids = [0];
            }
            $sql = self::where('artists.id IN (?)', $ids);
        } else {
            $sql = self::where(
                "(artists.name LIKE ? OR a2.name LIKE ?)",
                $name . "%",
                $name . "%",
            )
                   ->joins('LEFT JOIN artists a2 ON artists.alias_id = a2.id');
        }
        return $sql;
    }
}
