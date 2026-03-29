<template>
	<section class="gi-page gi-page--restricted">
		<header class="gi-page__header gi-page__header--dense">
			<div>
				<p class="gi-kicker">Consultas Legales</p>
				<h1>Sin acceso</h1>
				<p class="gi-page__subtitle">Tu usuario no tiene acceso a ninguna seccion disponible de esta app.</p>
			</div>
		</header>
		<div class="gi-empty-state gi-page__empty-state">
			<p>Si esta app debe estar disponible para ti, revisa la asignacion por grupos en Nextcloud o los perfiles configurados en la administracion de la app.</p>
		</div>
	</section>
</template>

<style scoped>
.gi-page--restricted {
	max-width: 64rem;
	margin: 0 auto;
}

.gi-page__empty-state {
	min-height: 18rem;
	padding: 2rem;
	text-align: center;
}

.gi-page__empty-state p {
	max-width: 42rem;
	margin: 0 auto;
	color: #526760;
}
</style>