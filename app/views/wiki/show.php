<?php $this->provide('title', str_replace('_', ' ', $this->params()->title)) ?>
<?php #render ['partial' => "sidebar"] ?>

<div class="wiki" id="wiki-show">
  <h2 class="title">
    <?php if ($this->tag) : ?>
      <?= $this->h($this->tag->pretty_type_name()) ?>:
    <?php endif ?>

    <?php if (!$this->page) : ?>
      <?= $this->h(str_replace("_", " ", $this->params()->title)) ?>
    <?php else: ?>
      <?= $this->h($this->page->pretty_title()) ?> <?php if (!$this->page->last_version()): ?><span class="old-version">(Version <?= $this->page->version ?>)</span><?php endif ?>
    <?php endif ?>
  </h2>

  <?php if (!$this->page && !$this->artist) : ?>
    <p>No page currently exists.</p>
  <?php endif ?>

  <?php if ($this->page) : ?>
    <div id="body">
      <?= $this->format_inlines($this->format_text($this->page->body), 1) ?>
    </div>
  <?php endif ?>

  <?php if ($this->artist) : ?>
    <div style="clear: both;">
     <table class="form" style="margin-bottom: 1em;">
       <tbody>
         <?php foreach($this->artist->artist_urls as $artist_url) : ?>
          <tr>
            <th>URL</th>
            <td>
              <?= $this->linkTo($artist_url->url, $artist_url->url) ?>
              <?php if (current_user()->is_mod_or_higher()) : ?>
                (<?= $this->linkTo("mass edit", ['controller' => "tag", 'action' => "mass_edit", 'source' => "-".$this->artist->name." source:" . ArtistUrl::normalize_for_search($artist_url->url), 'name' => $this->artist->name]) ?>)
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
        <?php if ($this->artist->alias_id) : ?>
          <tr>
            <th>Alias for</th>
            <td><?= $this->linkTo($this->artist->alias_name(), ['action' => "show", 'title' => $this->artist->alias_name()]) ?></td>
          </tr>
        <?php endif ?>
        <?php if ($this->artist->aliases()->any()) : ?>
          <tr>
            <th>Aliases</th>
            <td><?= implode(', ', array_map(function($x){return $this->linkTo($this->h($x->name), array('#show', 'title' => $x->name));}, $this->artist->aliases()->members())) ?></td>
          </tr>
        <?php endif ?>
        <?php if ($this->artist->group_id) : ?>
          <tr>
            <th>Member of</th>
            <td><?= $this->linkTo($this->artist->group_name(), ['action' => "show", 'title' => $this->artist->group_name()]) ?></td>
          </tr>
        <?php endif ?>
        <?php if ($this->artist->members()->any()) : ?>
          <tr>
            <th>Members</th>
            <td><?= implode(', ', array_map(function($x){return $this->linkTo($this->h($x->name), array('#show', 'title' => $x->name));}, $this->artist->members()->members())) ?></td>
          </tr>
        <?php endif ?>
       </tbody>
     </table>
    </div>
  <?php endif ?>

  <?php if ($this->posts) : ?>
    <ul id="post-list-posts" style="margin-top: 1em; margin-bottom: 1em; display: block; clear: both;">
      <?php foreach ($this->posts as $p) : ?>
        <?= $this->print_preview($p) ?>
      <?php endforeach ?>
    </ul>
  <?php endif ?>

  <?php if ($this->page) : ?>
    <div id="byline" style="clear: both;">Updated by <?= $this->linkTo($this->h($this->page->author()), ['controller' => "user", 'action' => "show", 'id' => $this->page->user_id]) ?> <?= $this->timeAgoInWords($this->page->updated_at) ?> ago</div>
  <?php endif ?>
  </div>

  <div class="footer" style="clear: both;">
  <?php if (!$this->page) : ?>
    <?= $this->linkTo("View Posts", ['controller' => "post", 'action' => "index", 'tags' => $this->params()->title]) ?> |
    <?= $this->linkTo("Edit", ['controller' => "wiki", 'action' => "edit", 'title' => $this->params()->title]) ?>
  <?php else: ?>
    <?= $this->linkToIf(!$this->page->first_version(), "<<", ['controller' => "wiki", 'action' => "show", 'title' => $this->page->title, 'version' => $this->page->version-1]) ?>
    <?= $this->linkTo("View posts", ['controller' => "post", 'action' => "index", 'tags' => $this->page->title]) ?>
    | <?= $this->linkTo("History", ['controller' => "wiki", 'action' => "history", 'title' => $this->page->title]) ?>
    <?php if (!$this->page->is_locked) : ?>
      | <?= $this->linkTo("Edit", ['controller' => "wiki", 'action' => "edit", 'title' => $this->page->title, 'version' => $this->page->version]) ?>
    <?php endif ?>
    <?php if ($this->page->is_locked) : ?>
      <?php if ($this->can_access('mod')) : ?>
      | <?= $this->linkTo("Unlock", ['controller' => "wiki", 'action' => "unlock", 'title' => $this->page->title], ['method' => "post"]) ?>
      <?php endif ?>
    <?php else: ?>
      | <?= $this->linkTo("Revert", ['controller' => "wiki", 'action' => "revert", 'title' => $this->page->title, 'version' => $this->page->version], ['level' => 'member', 'confirm' => "Are you sure you want to revert to this page?", 'method' => 'post']) ?>
      <?php if ($this->can_access('mod')) : ?>
      | <?= $this->linkTo("Delete", ['controller' => "wiki", 'action' => "destroy", 'title' => $this->page->title], ['confirm' => "Are you sure you want to delete this page (and all versions)?", 'method' => 'post']) ?>
      | <?= $this->linkTo("Lock", ['controller' => "wiki", 'action' => "lock", 'title' => $this->page->title], ['method' => 'post']) ?>
      | <?= $this->linkTo("Rename", ['action' => "rename", 'title' => $this->page->title]) ?>
      <?php endif ?>
    <?php endif ?>
    <?= $this->linkToIf(!$this->page->last_version(), ">>", ['controller' => "wiki", 'action' => "show", 'title' => $this->page->title, 'version' => $this->page->version + 1]) ?>
  <?php endif ?>
  <br>

  <?php $this->contentFor('subnavbar', function() { ?>
    <?php if ($this->artist) : ?>
      <li><?= $this->linkTo("Edit Artist", ['controller' => "artist", 'action' => "update", 'id' => $this->artist->id]) ?></li>
      <?php if ($this->can_access('privileged')) : ?>
        <li><?= $this->linkTo("Delete Artist", ['controller' => "artist", 'action' => "destroy", 'id' => $this->artist->id]) ?></li>
      <?php endif ?>
      <?php if (!$this->artist->alias_id) : ?>
        <li><?= $this->linkTo("Alias Artist", ['controller' => "artist", 'action' => "create", 'alias_id' => $this->artist->id]) ?></li>
      <?php endif ?>
    <?php elseif ($this->tag && $this->tag->type_name == "artist") : ?>
      <li><?= $this->linkTo('Create', ['controller' => 'artist', 'action' => 'create', 'name' => $this->title]) ?></li>
    <?php endif ?>
  <?php }) ?>

  <?= $this->partial("footer", ['omit_div' => true]) ?>
</div>

<script type="text/javascript">
  InlineImage.init();
</script>

<?php
# MI: Hide blacklisted posts.
if ($this->posts) :
?>
<script type="text/javascript">
Post.register_resp(<?= json_encode(Post::batch_api_data($this->posts->members())) ?>);
Post.init_blacklisted();
</script>
<?php endif ?>
