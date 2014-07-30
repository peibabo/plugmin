<?php get_header(); ?>

	<div class="col-md-9 single">

<!-- 		<div class="col-md-9 single-in"> -->
		<div class="col-md-12 single-in">

			<?php if (have_posts()) :?><?php while(have_posts()) : the_post(); ?>

				<?php $video = get_post_meta($post->ID, 'fullby_video', true );

				if($video != '') {?>

					<div class="videoWrapper">

						<div class='video-container'><iframe title='YouTube video player' width='400' height='275' src='http://www.youtube.com/embed/<?php echo $video; ?>' frameborder='0' allowfullscreen></iframe></div>

					</div>

				<?php

				} else if ( has_post_thumbnail() ) { ?>

					<?php the_post_thumbnail('single', array('class' => 'sing-cop')); ?>

				<?php } else { ?>

					<div class="row spacer-sing"> </div>

				<?php }  ?>


				<div class="sing-tit-cont">

					<p class="cat"> <?php the_category(','); ?></p>

					<h3 class="sing-tit"><?php the_title(); ?></h3>

					<p class="meta">

						<i class="fa fa-clock-o"></i> <?php the_time('j M , Y') ?>  &nbsp;

						<?php
						$video = get_post_meta($post->ID, 'fullby_video', true );

						if($video != '') { ?>

							<i class="fa fa-video-camera"></i> Video

						<?php } else if (strpos($post->post_content,'[gallery') !== false) { ?>

							<i class="fa fa-th"></i> Gallery

						<?php } else {?>

						<?php } ?>

					</p>

				</div>

				<div class="sing-cont">

					<div class="sing-spacer">

						<?php the_content('Leggi...');?>

						<?php wp_link_pages('pagelink=Page %'); ?>

						<p>
							<?php $post_tags = wp_get_post_tags($post->ID); if(!empty($post_tags)) {?>
								<span class="tag"> <i class="fa fa-tag"></i> <?php the_tags('', ', ', ''); ?> </span>
							<?php } ?>
						</p>

						<div class="pagelink">
							<div class="snsshare">
								<a id="share_fb" href="http://www.facebook.com/sharer.php?src=bm&u=<?php echo get_permalink(); ?>&amp;t=<?php the_title(); ?>" onclick="javascript:window.open(this.href, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=300,width=600');return false;">Facebookでシェア</a>
								<a id="share_tw" href="http://twitter.com/intent/tweet?url=http%3A%2F%2Fdout.jp%2F1308&amp;text=皮肉混じりに描かれた、デザイナのキャリアアップに必要なスキル。 dropout - 刺さる動画メディア %40dout_jp" onclick="javascript:window.open(this.href, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=300,width=600');return false;">Twitterでシェア</a>
							</div>
							このプラグイン良いかも！と思ったらシェア！
						</div>

						<hr />

						<div id="comments">

							<?php comments_template(); ?>

						</div>

					</div>

				</div>

			<?php endwhile; ?>
			<?php else : ?>

					<p>Sorry, no posts matched your criteria.</p>

			<?php endif; ?>

		</div>

<!-- 		<div class="col-md-3"> -->

<!-- 			<div class="sec-sidebar"> -->

				<?php // get_sidebar( 'secondary' ); ?>

<!-- 			</div> -->

<!-- 		</div> -->

	</div>

	<div class="col-md-3 sidebar">

		<?php get_sidebar( 'primary' ); ?>

	</div>

<?php get_footer(); ?>