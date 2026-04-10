<?php if (!$this->post->is_deleted()) : ?>
  <div<?php if (CONFIG()->dblclick_resize_image) {
      echo ' ondblclick="Post.resize_image(); return false;"';
  } ?>>
    <?php if (!$this->post->can_be_seen_by(current_user())) : ?>
      <p><?= $this->t('.text') ?></p>
    <?php elseif ($this->post->image()) : ?>
      <div id="note-container">
        <?php foreach ($this->post->active_notes() as $note) : ?>
          <div class="note-box" style="width: <?= $note->width ?>px; height: <?= $note->height ?>px; top: <?= $note->y ?>px; left: <?= $note->x ?>px;" id="note-box-<?= $note->id ?>">
            <div class="note-corner" id="note-corner-<?= $note->id ?>"></div>
          </div>
          <div class="note-body" id="note-body-<?= $note->id ?>" title="Click to edit"><?= $note->formatted_body() ?></div>
        <?php endforeach ?>
      </div>
      <?php
        $file_sample = $this->post->get_file_sample(current_user());
        $jpeg        = $this->post->get_file_jpeg(current_user());

        echo $this->imageTag($file_sample['url'], [
            'alt'           => $this->post->tags(),
            'id'            => 'image',
            'class'         => 'image',
            'width'         => $file_sample['width'] ?: $jpeg['width'],
            'height'        => $file_sample['height'] ?: $jpeg['height'],
            'large_width'   => $jpeg['width'],
            'large_height'  => $jpeg['height'],
            'fetchpriority' => 'high']); ?>
    <?php else: ?>
      <h2><a href="<?= $this->post->file_url() ?>"><?= $this->t('post_download') ?></a></h2>
      <p><?= $this->t('post_download_text') ?></p>
    <?php endif ?>
  </div>
  <div style="margin-bottom: 1em;">
    <p id="note-count"></p>
    <script type="text/javascript">
      jQuery('#image').on('mousedown', function(e){
        if (e.shiftKey) Note.dragCreate(e)
      })
      
      Note.post_id = <?= $this->post->id ?>

      <?php foreach ($this->post->active_notes() as $note) : ?>
        Note.all.push(new Note(<?= $note->id ?>, false, '<?= str_replace("\n", '\n', addslashes($this->h($note->body))) ?>'))
      <?php endforeach ?>

      Note.updateNoteCount()
      Note.show()

      jQuery(function() {
        var note_toggle = true;
        new WindowDragElement($("image"), {condition: function(){ if (Note.drag_created) return false; }, startdrag: function(){ note_toggle=false; }});
        $("image").observe("mouseup", function(e) { if(note_toggle) Note.toggle();else note_toggle=true; }.bindAsEventListener());
      });
    </script>
  </div>
<?php endif ?>

