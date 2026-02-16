<tr id="tag-subscription-row-<?= $this->tag_subscription->id ?>">
  <td><input onclick="new Ajax.Request('<?= $this->urlFor(['tag_subscription#destroy', 'id' => $this->tag_subscription->id, 'format' => 'js']) ?>', {asynchronous:true, evalScripts:true});" type="button" value="<?= '-' ?>"></td>
  <td><?= $this->textFieldTag("tag_subscription[".$this->tag_subscription->id."][name]", $this->tag_subscription->name, ['size' => 20, 'required']) ?></td>
  <td><?= $this->textFieldTag("tag_subscription[".$this->tag_subscription->id."][tag_query]", $this->tag_subscription->tag_query, ['size' => 70, 'required']) ?></td>
  <td>
    <?= $this->selectTag("tag_subscription[".$this->tag_subscription->id."][is_visible_on_profile]", $this->optionsForSelect(["Visible" => 1, "Hidden" => 0], $this->tag_subscription->is_visible_on_profile)) ?>
  </td>
</tr>
