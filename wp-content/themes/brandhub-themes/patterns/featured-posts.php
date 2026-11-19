<?php
/**
 * Title: BrandHub Featured Posts
 * Slug: brandhub-theme/featured-posts
 * Categories: query
 * Description: Three latest blog posts in a responsive grid.
 * Inserter: true
 */
?>
<!-- wp:group {"layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"40px","bottom":"40px"}}}} -->
<div class="wp-block-group" style="padding-top:40px;padding-bottom:40px">
  <!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Latest from BrandHub</h2><!-- /wp:heading -->
  <!-- wp:query {"query":{"perPage":3,"postType":"post","order":"desc","orderBy":"date"},"displayLayout":{"type":"flex","columns":3}} -->
  <div class="wp-block-query">
    <!-- wp:post-template -->
      <!-- wp:group {"style":{"spacing":{"blockGap":"10px"},"border":{"width":"1px","color":"#e2e8f0"},"padding":"16px"}} -->
      <div class="wp-block-group" style="border:1px solid #e2e8f0;padding:16px">
        <!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->
        <!-- wp:post-title {"isLink":true,"level":3} /-->
        <!-- wp:post-excerpt {"excerptLength":16} /-->
      </div>
      <!-- /wp:group -->
    <!-- /wp:post-template -->
  </div>
  <!-- /wp:query -->
</div>
<!-- /wp:group -->
