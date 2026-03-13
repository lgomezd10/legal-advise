const path = require('path')
const webpack = require('webpack')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
	main: path.join(__dirname, 'src', 'main.ts'),
}

webpackConfig.output = {
	...webpackConfig.output,
	clean: true,
	filename: '[name].js',
	chunkFilename: '[name].js',
}

webpackConfig.cache = false

webpackConfig.resolve = {
	...webpackConfig.resolve,
	alias: {
		...(webpackConfig.resolve?.alias ?? {}),
		'@': path.join(__dirname, 'src'),
	},
	extensions: ['.ts', '.js', '.vue'],
}

webpackConfig.module = {
	...webpackConfig.module,
	rules: (webpackConfig.module?.rules ?? []).map((rule) => {
		if (!(rule?.test instanceof RegExp) || rule.test.toString() !== /\.tsx?$/.toString()) {
			return rule
		}

		return {
			...rule,
			exclude: [/node_modules/, /tests/],
			use: [
				'babel-loader',
				{
					loader: 'ts-loader',
					options: {
						appendTsSuffixTo: [/\.vue$/],
						onlyCompileBundledFiles: true,
					},
				},
			],
		}
	}),
}

webpackConfig.plugins = [
	...(webpackConfig.plugins ?? []),
	new webpack.IgnorePlugin({
		resourceRegExp: /\.(test|spec)\.[jt]sx?$/,
	}),
]

module.exports = webpackConfig