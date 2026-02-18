<div class="status-notice" id="pool<?= $this->pool->id ?>">
  <div style="display: inline;">
    <p>
      <?php if ($this->pool_post->prev_post_id) : ?>
        <?= $this->linkTo($this->t('.previous'), array('#show', 'id' => $this->pool_post->prev_post_id, 'pool_id' => $this->pool_post->pool_id)) ?>
      <?php endif ?>
      <?php if ($this->pool_post->next_post_id) : ?>
        <?= $this->linkTo($this->t('.next'), array('#show', 'id' => $this->pool_post->next_post_id, 'pool_id' => $this->pool_post->pool_id)) ?>
      <?php endif ?>
      <?= $this->t(['.info_html', 'sequence' => $this->contentTag('span', $this->h($this->pool_post->pretty_sequence()), ['id' => 'pool-seq-' . $this->pool_post->pool_id]), 'pool' => $this->linkTo($this->h($this->pool->pretty_name()), array('pool#show', 'id' => $this->pool->id))]) ?>
      <?php $this->pooled_post_id = $this->post->id ?>

    <?php if (current_user()->can_change($this->pool_post, 'active')) : ?>
      <span class="advanced-editing">
        (<?= $this->linkToFunction($this->t('.remove._'), "if(confirm('" . $this->escapeJavascript($this->t(['.remove.confirm', 'current_pool' => $this->pool->pretty_name()])) . "')) Pool.remove_post(".$this->post->id.", ".$this->pool->id.")");
        ?>, <?= $this->linkToFunction($this->t('.change'), "Pool.change_sequence(" . $this->post->id . ", " . $this->pool_post->pool_id . ", '" . $this->escapeJavascript($this->pool_post->sequence) . "')");
        ?><?php
          if ($this->post->parent_id)
            echo $this->linkToFunction($this->t('.transfer._'), "if(confirm('" . $this->escapeJavascript($this->t(['.transfer.confirm', 'current_pool' => $this->pool->pretty_name()])) . "')) Pool.transfer_post(".$this->post->id.", ".$this->post->parent_id.", ".$this->pool->id . ", '" . $this->escapeJavascript($this->pool_post->sequence) . "')");
        ?>)
      </span>
    <?php endif ?>
    </p>
  </div>
</div>
