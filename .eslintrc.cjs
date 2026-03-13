module.exports = {
	extends: ['@nextcloud'],
	root: true,
	parserOptions: {
		ecmaVersion: 'latest',
		sourceType: 'module',
	},
	env: {
		browser: true,
		es2022: true,
	},
}