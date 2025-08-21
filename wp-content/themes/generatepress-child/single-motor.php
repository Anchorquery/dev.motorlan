<?php
/**
 * Template for displaying single Motor posts
 */
get_header();

// Gather ACF fields
$tipo       = get_field('tipo_o_referencia');
$potencia   = get_field('potencia');
$velocidad  = get_field('velocidad');
$marca      = get_field('marca');
$precio     = get_field('precio_de_venta');
$imagen     = get_field('motor_image');
$galeria    = get_field('motor_gallery');
$documento  = get_field('informe_de_reparacion');
?>

<main class="motor-detail container">
  <section class="motor-top">
    <div id="motor-gallery" class="motor-gallery">
      <?php if( $imagen ): ?>
        <a href="<?php echo esc_url($imagen['url']); ?>">
          <img src="<?php echo esc_url($imagen['url']); ?>" alt="<?php the_title_attribute(); ?>" />
        </a>
      <?php endif; ?>
      <?php if( $galeria ):
        foreach( $galeria as $img ): ?>
          <a href="<?php echo esc_url($img['url']); ?>">
            <img src="<?php echo esc_url($img['url']); ?>" alt="<?php the_title_attribute(); ?>" />
          </a>
        <?php endforeach;
      endif; ?>
    </div>

    <div class="motor-info-card">
      <h1 class="motor-title"><?php echo esc_html( get_the_title() ); ?></h1>
      <p class="motor-meta">
        <?php echo esc_html( trim("$tipo $potencia $velocidad") ); ?>
      </p>
      <?php if( $precio ): ?>
        <p class="motor-price"><?php echo esc_html( $precio ); ?>€</p>
      <?php endif; ?>
      <div class="motor-actions">
        <button class="buy">Comprar</button>
        <button class="offer">Hacer una oferta</button>
      </div>

      <div class="contact-form">
        <h2>Contactar ahora</h2>
        <form>
          <textarea name="mensaje" placeholder="Mensaje"></textarea>
          <input type="text" name="nombre" placeholder="Nombre" />
          <input type="email" name="email" placeholder="Email" />
          <input type="tel" name="telefono" placeholder="Teléfono" />
          <button type="submit">Enviar</button>
        </form>
      </div>
    </div>
  </section>

  <section class="motor-info-table">
    <h2>Información del motor</h2>
    <table>
      <tbody>
        <?php if( $marca ): ?>
        <tr><th>Marca</th><td><?php echo esc_html($marca); ?></td></tr>
        <?php endif; ?>
        <?php if( $tipo ): ?>
        <tr><th>Tipo</th><td><?php echo esc_html($tipo); ?></td></tr>
        <?php endif; ?>
        <?php if( $potencia ): ?>
        <tr><th>Potencia</th><td><?php echo esc_html($potencia); ?></td></tr>
        <?php endif; ?>
        <?php if( $velocidad ): ?>
        <tr><th>Velocidad</th><td><?php echo esc_html($velocidad); ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="motor-docs">
    <h2>Documentación adicional</h2>
    <?php if( $documento ): ?>
      <a href="<?php echo esc_url($documento['url']); ?>" target="_blank">Descargar informe</a>
    <?php else: ?>
      <p>No hay documentación disponible.</p>
    <?php endif; ?>
  </section>

  <section class="motor-related">
    <h2>Productos relacionados</h2>
    <div class="related-products">
    <?php
    $related_query = new WP_Query( array(
      'post_type'      => 'motor',
      'posts_per_page' => 4,
      'post__not_in'   => array( get_the_ID() ),
    ) );
    if ( $related_query->have_posts() ):
      while( $related_query->have_posts() ): $related_query->the_post();
        $thumb = get_field('motor_image');
        $price = get_field('precio_de_venta');
    ?>
      <article class="product">
        <a href="<?php the_permalink(); ?>">
          <?php if( $thumb ): ?>
            <img src="<?php echo esc_url( $thumb['sizes']['medium'] ); ?>" alt="<?php the_title_attribute(); ?>" />
          <?php endif; ?>
          <h3><?php the_title(); ?></h3>
          <?php if( $price ): ?><span class="price"><?php echo esc_html($price); ?>€</span><?php endif; ?>
        </a>
      </article>
    <?php
      endwhile;
      wp_reset_postdata();
    endif;
    ?>
    </div>
  </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const gallery = document.getElementById('motor-gallery');
  if (gallery && typeof lightGallery === 'function') {
    lightGallery(gallery, { thumbnail: true });
  }
});
</script>

<?php get_footer(); ?>
