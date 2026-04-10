<?php if (!CONFIG()->enable_news_ticker) {
    return;
} ?>
<div id="news-ticker" style="display: none">
  <ul>
    <li>MyImouto is now on <a href="https://github.com/Wachhund/myimouto">GitHub</a>.</li>
  </ul>

  <a href="#" id="close-news-ticker-link"><?= $this->t('.close') ?></a>
</div>
