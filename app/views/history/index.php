<?php $this->provide('title', $this->t('.title')) ?>
<div>
  <div style="margin-bottom: 1em;">
    <ul class="history-header">
      <?php if ($this->type == "all" || $this->type == "posts") : ?>
        <li>» <?= $this->linkTo($this->options['show_all_tags'] ? $this->t('.show.all') : $this->t('.show.changed'), array_merge(['#index'], array_merge($this->params()->get(), ['show_all_tags' => $this->options['show_all_tags'] ? 0:1]))) ?></li>
      <?php endif ?>

      <?php /* If we're searching for a specific object, omit the id/name' column and
          show it once at the top. */ ?>
      <?php if ($this->options['specific_object'] && $this->changes->any()) : ?>
        <li>
          » <?= $this->t('.for') ?> <?= $this->singularize($this->type) ?>:
          <?=
          $this->linkTo(
            $this->options['show_name'] ?
                $this->changes[0]->group_by_obj->pretty_name() :
                $this->changes[0]->group_by_id,
            ['controller' => strtolower($this->changes[0]->get_group_by_controller()), 'action' => "show", 'id' => $this->changes[0]->group_by_id]
          )
          ?>
        </li>
      <?php endif ?>
    </ul>
  </div>

  <div style="clear: left;">
    <?= $this->imageTag('images/blank.gif', ['id' => 'hover-thumb', 'alt' => '', 'style' => 'position: absolute; display: none; border: 2px solid #000; right: 10%']) ?>

    <table width="100%" class="highlightable" id="history">
      <thead>
        <tr>
          <?php if ($this->type == "all") : ?>
            <th><?= $this->t('.object_type') ?></th>
          <?php endif ?>
          <th></th>
          <?php if ($this->options['specific_object']) : ?>
          <?php elseif ($this->options['show_name']) : ?>
            <th><?= $this->capitalize($this->singularize($this->type)) ?></th>
          <?php else: ?>
            <th><?php
                if ($this->type == "all")
                    echo $this->t('.id');
                else
                    echo ucfirst($this->singularize($this->type));
              ?></th>
          <?php endif ?>
          <th><?= $this->t('.date') ?></th>
          <th><?= $this->t('.user') ?></th>
          <th><?= $this->t('.change') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($this->changes as $change) : ?>
          <?php $new_user = (time() - strtotime($change->user->created_at) < 60*60*24*3) ?>
          <tr class="<?= $this->cycle('even', 'odd') ?>" id="r<?= $change->id ?>">
            <?php if ($this->type == "all") : ?>
              <td><?= $this->humanize($this->singularize($change->group_by_table)) ?></td>
            <?php endif ?>

            <td style="background: <?= $this->id_to_color($change->group_by_id) ?>;"></td>
            <?php if (!$this->options['specific_object']) : ?>
              <?php
              $classes = ["id"];
              if (get_class($change->group_by_obj()) == "Post") {
                if ($change->group_by_obj()->status == "deleted")
                    $classes[] = "deleted";
                if ($change->group_by_obj()->is_held)
                    $classes[] = "held";
              }
              ?>
              <td class="<?= implode(" ", $classes) ?>"><?= $this->linkTo($this->options['show_name'] ? $change->group_by_obj()->pretty_name() : $change->group_by_id, [
                'controller' => strtolower($change->get_group_by_controller()),
                'action' => $change->get_group_by_action(),
                'id' => $change->group_by_id]
              ) ?></td>
            <?php endif ?>
            <td><?= date("M d Y, H:i", strtotime($change->created_at)) ?></td>
            <td class="author"><?= $this->linkToIf($change->user_id, $change->author(),
              ['controller' => "user", 'action' => "show", 'id' => $change->user_id],
              ['class' => "user-" . $change->user_id . ($new_user ? " new-user":"")]
            ) ?></td>
            <td class="change"><?= $this->format_changes($change, $this->options) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
    <div class="history-search-row">
      <div>
        <?= $this->formTag("#index", ['method' => 'get'], function(){ ?>
          <?= $this->textFieldTag("search", $this->params()->search, ['id' => "search", 'size' => 20]) ?> <?= $this->submitTag($this->t('.search')) ?>
        <?php }) ?>
      </div>
    </div>

  <div class="footer history-footer">
    <?= $this->linkToFunction($this->t('.undo'), "History.undo(false)", ['level' => 'member', 'id' => "undo"]) ?> |
    <?= $this->linkToFunction($this->t('.redo'), "History.undo(true)", ['level' => 'member', 'id' => "redo"]) ?>
  </div>
  <?php $this->contentFor('subnavbar', function(){ ?>
    <li><?= $this->linkTo($this->t('.type.all'), ['action' => "index"]) ?></li>
    <li><?= $this->linkTo($this->t('.type.posts'), ['action' => "index", 'search' => "type:posts"]) ?></li>
    <li><?= $this->linkTo($this->t('.type.pools'), ['action' => "index", 'search' => "type:pools"]) ?></li>
    <li><?= $this->linkTo($this->t('.type.tags'), ['action' => "index", 'search' => "type:tags"]) ?></li>
  <?php }) ?>
</div>

<?php $this->contentFor('post_cookie_javascripts', function() { ?>
<script type="text/javascript">
  var thumb = $("hover-thumb");
  <?php foreach ($this->changes as $change) : ?>
    History.add_change(<?= $change->id ?>, "<?= $change->get_group_by_controller() ?>", <?= $change->group_by_id ?>, [ <?= implode(', ', $change->history_changes->getAttributes('id')) ?> ], '<?= $this->escapeJavascript($change->author()) ?>')
    <?php if ($change->group_by_table_class() == "Post" && CONFIG()->can_see_post(current_user(), $change->group_by_obj())) : ?>
      Post.register(<?= $this->jsonEscape($change->group_by_obj()->toJson()) ?>)
      var hover_row = $("r<?= $change->id ?>");
      var container = hover_row.up("TABLE");
      Post.init_hover_thumb(hover_row, <?= $change->group_by_id ?>, thumb, container);
    <?php endif ?>
  <?php endforeach ?>
  Post.init_blacklisted({replace: true});

  <?php foreach ($this->changes as $change) : ?>
    <?php if ($change->group_by_table_class() == "Post" && CONFIG()->can_see_post(current_user(), $change->group_by_obj())) : ?>
      if(!Post.is_blacklisted(<?= $change->group_by_obj()->id ?>))
        Preload.preload('<?= $this->escapeJavascript($change->group_by_obj()->preview_url()) ?>');
    <?php endif ?>
  <?php endforeach ?>
  History.init()
</script>
<?php }) ?>

<div id="paginator">
  <?= $this->willPaginate($this->changes) ?>
</div>
