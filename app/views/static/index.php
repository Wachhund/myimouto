<div id="static-index">
  <h1 id="static-index-header"><?= $this->linkTo(CONFIG()->app_name, "/") ?></h1>
  <div style="margin-bottom: 1em;" id="links">
    <?= $this->linkTo($this->t('static_posts'), 'post#index', ['title' => $this->t('static_posts_text')]) ?>
    <?= $this->linkTo($this->t('static_comments'), 'comment#index', ['title' => $this->t('static_comments_text')]) ?>
    <?= $this->linkTo($this->t('static_tags'), 'tag#index', ['title' => $this->t('static_tags_text')]) ?>
    <?= $this->linkTo($this->t('static_wiki'), 'wiki#', ['title' => $this->t('static_wiki_text')]) ?>
    <?= $this->linkTo('»', 'static#more', ['title' => $this->t('static_more')]) ?>
  </div>
  <div style="margin-bottom: 2em;">
    <?= $this->formTag('post#index', ['method' => "get"], function () { ?>
      <div>
        <?= $this->textFieldTag("tags", "", ['size' => 30]) ?><br />
        <?= $this->submitTag($this->t('static_search')) ?>
      </div>
    <?php }) ?>
  </div>
  <?= $this->numbers_to_imoutos($this->post_count) ?>
  <div style="font-size: 80%; margin-bottom: 2em;">
    <p>
      <?php if (current_user()->is_member_or_higher()) : ?>
        <?= $this->mailTo(CONFIG()->admin_contact, "Contact", ['encode' => "javascript"]) ?> &ndash;
      <?php endif ?>
      <?= $this->t('static_serve') ?><?= number_format($this->post_count, 0) ?><?= $this->t('static_posts2') ?>&ndash; <?= str_replace('Moebooru', 'MyImouto', $this->t('static_running')) ?><?= CONFIG()->version ?>
      <br />
      <?= $this->t('static_translation') ?>
    </p>
  </div>
</div>

<?php
if (CONFIG()->enable_tag_completion && CONFIG()->tag_completion_in_homepage) {
    echo $this->tag_completion_box('$("tags")', [], true);
}
  ?> 
