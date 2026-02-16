<tr id="tag-subscription-row-<?= $this->tag_subscription->id ?>">
  <?php $destroy_url = '/tag_subscription/destroy/' . (int)$this->tag_subscription->id . '.js'; ?>
  <td><input onclick="new Ajax.Request('<?= $destroy_url ?>', {asynchronous:true, evalScripts:true, parameters:'csrf_token=<?= rawurlencode($this->csrf_token) ?>'});" type="button" value="<?= '-' ?>"></td>
  <td><?= $this->textFieldTag("tag_subscription[".$this->tag_subscription->id."][name]", $this->h($this->tag_subscription->name), ['size' => 20, 'required']) ?></td>
  <td><?= $this->textFieldTag("tag_subscription[".$this->tag_subscription->id."][tag_query]", $this->h($this->tag_subscription->tag_query), ['size' => 70, 'required']) ?></td>
  <td>
    <?= $this->selectTag("tag_subscription[".$this->tag_subscription->id."][is_visible_on_profile]", $this->optionsForSelect(["Visible" => 1, "Hidden" => 0], $this->tag_subscription->is_visible_on_profile)) ?>
  </td>
</tr>
