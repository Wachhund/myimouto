<div class="clearfix" id="main-menu" data-controller="<?= $this->request()->controller() ?>">
  <?php
  /**
   * MI: how about caching the menu for each user level, instead of each user,
   * having placeholders for user id and name that will be replaced with current user's
   * data right before echoing the menu.
   */
  $key  = 'menu.' . Rails::application()->I18n()->locale() . '.'.current_user()->level;
  $menu = Rails::cache()->read($key);

  if (!$menu) :
    ob_start();
  ?>
    <ul>
      <li class="user"><?= $this->linkTo($this->t('.account._'), ['user#home'], ['onclick' => 'if(!User.run_login_onclick(event)) return false;', 'class' => 'login-button']) ?>
        <?= $this->linkTo('■', '#', ['class' => 'submenu-button']) ?>
        <ul class="submenu">
          <?php if (current_user()->is_anonymous()) : ?>
            <li><?= $this->linkTo($this->t('.account.login'), ['controller' => 'user', 'action' => 'login'], ['id' => 'login-link', 'class' => 'login-button']) ?></li>
            <li><?= $this->linkTo($this->t('.account.reset'), ['controller' => 'user', 'action' => 'reset_password']) ?></li>
          <?php else: ?>
            <li><?= $this->linkTo($this->t('.account.profile'), ['controller' => 'user', 'action' => 'show', 'id' => "-user.id-"]) // MI: -user.id- ?></li>
            <li><?= $this->linkTo($this->t('.account.mail'), ['controller' => 'dmail', 'action' => 'inbox']) ?></li>
            <li><?= $this->linkTo($this->t('.account.favorites'), ['controller' => 'post', 'action' => 'index', 'tags' => "order:vote vote:3:-user.name-"]) // MI: -user.name- ?></li>
            <li><?= $this->linkTo($this->t('.account.settings'), ['controller' => 'user', 'action' => 'edit']) ?></li>
            <li><?= $this->linkTo($this->t('.account.change_password'), ['controller' => 'user', 'action' => 'change_password']) ?></li>
            <li><?= $this->linkTo($this->t('.account.logout'), ['controller' => 'user', 'action' => 'logout']/* (MI: Since menu is cached, this doesn't make sense) , ['from' => $this->request()->path()]*/) ?></li>
          <?php endif ?>
        </ul>
      </li>
      <li class="post"><?= $this->linkTo($this->t('.posts._'), ['controller' => 'post', 'action' => 'index']) ?>
        <?= $this->linkTo('■', '#', ['class' => 'submenu-button']) ?>
        <ul class="search-box">
          <li>
            <div>
              <?= $this->formTag('post#', ['method' => 'get'], function(){ ?>
                <?= $this->textFieldTag('tags', '', ['id' => '']) ?><br />
                <?= $this->submitTag($this->t('.posts.search')) ?>
              <?php }) ?>
            </div>
          </li>
        </ul>
        <ul class="submenu">
          <li><?= $this->linkTo($this->t('.posts.view'), ['controller' => 'post', 'action' => 'index']) ?></li>
          <li><?= $this->linkTo($this->t('.posts.search'), ['controller' => 'post', 'action' => 'index'], ['class' => 'search-link']) ?></li>
          <li><?= $this->linkTo($this->t('.posts.upload'), ['controller' => 'post', 'action' => 'upload']) ?></li>
          <li><?= $this->linkTo($this->t('.posts.random'), ['controller' => 'post', 'tags' => 'order:random']) ?></li>
          <li><?= $this->linkTo($this->t('.posts.popular'), ['controller' => 'post', 'action' => 'popular_recent']) ?></li>
          <li><?= $this->linkTo($this->t('.posts.image_search'), ['controller' => 'post', 'action' => 'similar']) ?></li>
          <li><?= $this->linkTo($this->t('.posts.history'), ['controller' => 'history', 'action' => 'index']) ?></li>
          <?php if (current_user()->is_contributor_or_higher()) : ?>
            <li><?= $this->linkTo($this->t('.posts.batch'), ['controller' => 'batch', 'action' => 'index']) ?></li>
          <?php endif ?>
          <?php if (current_user()->is_janitor_or_higher()) : ?>
            <li><?= $this->linkTo($this->t('.posts.moderate'), ['controller' => 'post', 'action' => 'moderate'], ['class' => 'moderate']) ?></li>
          <?php endif ?>
          <?php if (current_user()->is_admin_or_higher()) : ?>
            <li><?= $this->linkTo($this->t('.posts.import'), ['post#import']) ?></li>
          <?php endif ?>
        </ul>
      </li>
      <li class="comment"><?= $this->linkTo($this->t('.comments._'), ['controller' => 'comment', 'action' => 'index'], ['id' => 'comments-link']) ?>
        <?= $this->linkTo('■', '#', ['class' => 'submenu-button']) ?>
        <ul class="search-box">
          <li>
            <div>
              <?= $this->formTag('comment#search', ['method' => 'get'], function(){ ?>
                <?= $this->textFieldTag('query', '') ?><br />
                <?= $this->submitTag($this->t('.comments.search')) ?>
              <?php }) ?>
            </div>
          </li>
        </ul>
        <ul class="submenu">
          <li><?= $this->linkTo($this->t('.comments.view'), ['controller' => 'comment', 'action' => 'index']) ?></li>
          <li><?= $this->linkTo($this->t('.comments.search'), ['controller' => 'comment', 'action' => 'search'], ['class' => 'search-link']) ?></li>
          <?php if (current_user()->is_janitor_or_higher()) : ?>
            <li><?= $this->linkTo($this->t('.comments.moderate'), ['controller' => 'comment', 'action' => 'moderate']) ?></li>
          <?php endif ?>
        </ul>
      </li>
      <li class="note"><?= $this->linkTo($this->t('.notes._'), ['controller' => 'note', 'action' => 'index']) ?>
        <?= $this->linkTo('■', '#', ['class' => 'submenu-button']) ?>
        <ul class="search-box">
          <li>
            <div>
              <?= $this->formTag('note#search', ['method' => 'get'], function(){ ?>
                <?= $this->textFieldTag('query', '') ?><br />
                <?= $this->submitTag($this->t('.notes.search')) ?>
              <?php }) ?>
            </div>
          </li>
        </ul>
        <ul class="submenu">
          <li><?= $this->linkTo($this->t('.notes.view'), ['controller' => 'note', 'action' => 'index']) ?></li>
          <li><?= $this->linkTo($this->t('.notes.search'), ['controller' => 'note', 'action' => 'search'], ['class' => 'search-link']) ?></li>
          <li><?= $this->linkTo($this->t('.notes.requests'), ['controller' => 'post', 'action' => 'index', 'tags' => 'translation_request']) ?></li>
        </ul>
      </li>
      <li class="artist"><?= $this->linkTo($this->t('.artists._'), ['controller' => 'artist', 'action' => 'index', 'order' => 'date']) ?>
        <?= $this->linkTo('■', '#', ['class' => 'submenu-button']) ?>
        <ul class="search-box">
          <li>
            <div>
              <?= $this->formTag('artist#index', ['method' => 'get'], function(){ ?>
                <?= $this->textFieldTag('name', '') ?><br />
                <?= $this->submitTag($this->t('.artists.search')) ?>
              <?php }) ?>
            </div>
          </li>
        </ul>
        <ul class="submenu">
          <li><?= $this->linkTo($this->t('.artists.view'), ['controller' => 'artist', 'action' => 'index']) ?></li>
          <li><?= $this->linkTo($this->t('.artists.search'), ['controller' => 'artist', 'action' => 'index'], ['class' => 'search-link']) ?></li>
          <li><?= $this->linkTo($this->t('.artists.create'), ['controller' => 'artist', 'action' => 'create']) ?></li>
        </ul>
      </li>
      <li class="tag"><?= $this->linkTo($this->t('.tags._'), ['controller' => 'tag', 'action' => 'index', 'order' => 'date']) ?>
        <?= $this->linkTo('■', '#', ['class' => 'submenu-button']) ?>
        <ul class="search-box">
          <li>
            <div>
              <?= $this->formTag('tag#index', ['method' => 'get'], function(){ ?>
                <?= $this->textFieldTag('name', '') ?><br />
                <?= $this->submitTag($this->t('.tags.search')) ?>
              <?php }) ?>
            </div>
          </li>
        </ul>
        <ul class="submenu">
          <li><?= $this->linkTo($this->t('.tags.view'), ['controller' => 'tag', 'action' => 'index']) ?></li>
          <li><?= $this->linkTo($this->t('.tags.search'), ['controller' => 'tag', 'action' => 'index'], ['class' => 'search-link']) ?></li>
          <li><?= $this->linkTo($this->t('.tags.popular'), ['controller' => 'tag', 'action' => 'popular_by_day']) ?></li>
          <li><?= $this->linkTo($this->t('.tags.aliases'), ['controller' => 'tag_alias', 'action' => 'index']) ?></li>
          <li><?= $this->linkTo($this->t('.tags.implications'), ['controller' => 'tag_implication', 'action' => 'index']) ?></li>
          <?php if (current_user()->is_janitor_or_higher()) : ?>
            <li><?= $this->linkTo($this->t('.tags.mass_edit'), ['controller' => 'tag', 'action' => 'mass_edit']) ?></li>
          <?php endif ?>
        </ul>
      </li>
      <li class="pool"><?= $this->linkTo($this->t('.pools._'), ['controller' => 'pool', 'action' => 'index']) ?>
        <?= $this->linkTo('■', '#', ['class' => 'submenu-button']) ?>
        <ul class="search-box">
          <li>
            <div>
              <?= $this->formTag('pool#index', ['method' => 'get'], function(){ ?>
                <?= $this->textFieldTag('query', '') ?><br />
                <?= $this->submitTag($this->t('.pools.search')) ?>
              <?php }) ?>
            </div>
          </li>
        </ul>
        <ul class="submenu">
          <li><?= $this->linkTo($this->t('.pools.view'), ['controller' => 'pool', 'action' => 'index']) ?></li>
          <li><?= $this->linkTo($this->t('.pools.search'), ['controller' => 'pool', 'action' => 'index'], ['class' => 'search-link']) ?></li>
          <li><?= $this->linkTo($this->t('.pools.create'), ['controller' => 'pool', 'action' => 'create']) ?></li>
        </ul>
      </li>
      <li class="wiki"><?= $this->linkTo($this->t('.wiki._'), (!CONFIG()->menu_wiki_link ? ['wiki#index'] : array_merge(['wiki#show'], CONFIG()->menu_wiki_link))) ?>
        <?= $this->linkTo('■', '#', ['class' => 'submenu-button']) ?>
        <ul class="search-box">
          <li>
            <div>
              <?= $this->formTag('wiki#index', ['method' => 'get'], function(){ ?>
                <?= $this->textFieldTag('query', '') ?><br />
                <?= $this->submitTag($this->t('.wiki.search')) ?>
              <?php }) ?>
            </div>
          </li>
        </ul>
        <ul class="submenu">
          <li><?= $this->linkTo($this->t('.wiki.index'), ['controller' => 'wiki', 'action' => 'index']) ?></li>
          <li><?= $this->linkTo($this->t('.wiki.search'), ['controller' => 'wiki', 'action' => 'index'], ['class' => 'search-link']) ?></li>
          <li><?= $this->linkTo($this->t('.wiki.create'), ['controller' => 'wiki', 'action' => 'add']) ?></li>
        </ul>
      </li>
      <li class="forum"><?= $this->linkTo($this->t('.forum._'), ['controller' => 'forum', 'action' => 'index'], ['id' => 'forum-link']) ?>
        <?= $this->linkTo('■', '#', ['class' => 'submenu-button']) ?>
        <ul class="search-box">
          <li>
            <div>
              <?= $this->formTag('forum#search', ['method' => 'get'], function(){ ?>
                <?= $this->textFieldTag('query', '') ?><br />
                <?= $this->submitTag($this->t('.forum.search')) ?>
              <?php }) ?>
            </div>
          </li>
        </ul>
        <ul class="submenu">
          <li><?= $this->linkTo($this->t('.forum.view'), ['controller' => 'forum', 'action' => 'index']) ?></li>
          <li><?= $this->linkTo($this->t('.forum.search'), ['controller' => 'forum', 'action' => 'index'], ['class' => 'search-link']) ?></li>
          <li><?= $this->linkTo($this->t('.forum.new'), ['controller' => 'forum', 'action' => 'new']) ?></li>
          <li><?= $this->linkTo($this->t('.forum.mark_all_read'), ['controller' => 'forum', 'action' => 'mark_all_read'], ['id' => 'forum-mark-all-read', 'style' => 'display: none;']) ?></li>
          <li class="forum-items-start"><span class="separator"></span></li>
        </ul>
      </li>
      <li class="help"><?= $this->linkTo($this->t('.help._'), "help#") ?>
        <?= $this->linkTo('■', '#', ['class' => 'submenu-button']) ?>
        <ul class="submenu">
          <?php # FIXME: should pluralize everything one day ?>
          <?php foreach(['post', 'comment', 'note', 'artist', 'tag', 'pool'] as $item) : ?>
            <li><?= $this->linkTo($this->t(".help." . $item . "s"), "help#" . $item . "s", ['class' => ['help-item', $item]]) ?>
          <?php endforeach ?>
          <?php foreach (['wiki', 'forum'] as $item) : ?>
            <li><?= $this->linkTo($this->t(".help.${item}"), "help#" . $item, ['class' => ['help-item', $item]]) ?>
          <?php endforeach ?>
          <li><?= $this->linkTo($this->t('.help.site'), "help#") ?></li>
        </ul>
      </li>

      <li class="static"><?= $this->linkTo($this->t('.more'), ['controller' => 'static', 'action' => 'more']) ?>
      </li>
      <li class="has-mail">
        <?= $this->linkTo($this->t('.new_mail'), ['controller' => 'dmail', 'action' => 'inbox'], ['id' => 'has-mail-notice']) ?>
      </li>
    </ul>
    <span id='cn' style="display: none;">
    </span>
  <?php
    $menu = ob_get_clean();
    Rails::cache()->write($key, $menu);
  endif;

  if (!current_user()->is_anonymous()) {
    $menu = substr_replace($menu,   current_user()->id, strpos($menu, '-user.id-'),    9);
    echo    substr_replace($menu, current_user()->name, strpos($menu, '-user.name-'), 11);
  } else {
    echo $menu;
  }
  ?>
</div>
