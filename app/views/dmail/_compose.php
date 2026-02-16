<?= $this->formTag("#create", function(){ ?>
  <?= $this->hiddenFieldTag("dmail[parent_id]", ($this->dmail->parent_id ?: $this->dmail->id), ['id' => "dmail_parent_id"]) ?>

  <table width="100%">
    <tfoot>
      <tr>
        <td></td>
        <td><?= $this->submitTag($this->t('.send')) ?> <?= $this->submitTag($this->t('.preview'), ['id' => 'dmail-preview', 'name' => 'preview']) ?></td>
      </tr>
      <tr>
        <td></td>
        <td><div style="width: 400px" id="dmail-preview-area"></div></td>
      </tr>
    </tfoot>
    <tbody>
      <tr>
        <th><label for="dmail_to_name"><?= $this->t('.form.to') ?></label></th>
        <td><input class="ac-user-name ui-autocomplete-input" id="dmail_to_name" name="dmail[to_name]" size="30" type="text" value="<?= $this->params()->to ?>" autocomplete="off" role="textbox" aria-autocomplete="list" aria-haspopup="true"/></td>
      </tr>
      <tr>
        <th><label for="dmail_title"><?= $this->t('.form.title') ?></label></th>
        <td>
          <input type="text" id="dmail_title" name="dmail[title]" value="<?= $this->h($this->dmail->title) ?>" />
        </td>
      </tr>
      <tr>
        <th><label for="dmail_body"><?=$this->t('.form.body') ?></label></th>
        <td>
          <textarea id="dmail_body" cols="50" name="dmail[body]" rows="25" class="default"><?= $this->h($this->dmail->body) ?></textarea>
        </td>
      </tr>
    </tbody>
  </table>
<?php }) ?>
<script type="text/javascript">
jQuery('#dmail-preview').on('click', function(ev){
  ev.preventDefault();
  jQuery('#dmail-preview-area').html('<em><?= $this->t('.preview_loading') ?></em>').
    load('<?= addslashes($this->urlFor(['dmail#preview'])) ?>', { body: jQuery('#dmail_body').val() })
});
(function($){
var e = $('#dmail_title');
e.val(e.val().replace(/^Re: /, ''));
})(jQuery);
</script>
