module.exports = {
	root: true,
	parser: 'vue-eslint-parser',
	extends: [
		'eslint:recommended',
		'plugin:vue/vue3-essential',
		'plugin:@typescript-eslint/recommended',
	],
	plugins: ['@typescript-eslint'],
	parserOptions: {
		parser: '@typescript-eslint/parser',
		ecmaVersion: 'latest',
		sourceType: 'module',
		extraFileExtensions: ['.vue'],
	},
	env: {
		browser: true,
		node: true,
		es2020: true,
	},
	ignorePatterns: ['js/**', 'node_modules/**'],
	overrides: [
		{
			files: ['**/*.d.ts'],
			rules: {
				'@typescript-eslint/no-explicit-any': 'off',
			},
		},
	],
	rules: {
		'no-undef': 'off',
		'no-unused-vars': 'off',
		'@typescript-eslint/no-unused-vars': ['warn', {
			argsIgnorePattern: '^_',
			varsIgnorePattern: '^_',
		}],
		'vue/multi-word-component-names': 'off',
	},
}