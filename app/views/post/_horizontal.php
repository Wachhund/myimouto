<div style="margin-bottom: 1em;">
  <?= $this->print_advertisement(
      "horizontal",
      !$this->localExists('position') ? null : $this->position,
      !$this->localExists('center') ? false : $this->center,
  )
  ?>
</div>
