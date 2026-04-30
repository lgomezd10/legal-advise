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
	publicPath: 'auto',
}

webpackConfig.cache = false

webpackConfig.optimization = {
	...(webpackConfig.optimization ?? {}),
	runtimeChunk: false,
	splitChunks: {
		...(webpackConfig.optimization?.splitChunks ?? {}),
		chunks: 'async',
		cacheGroups: {
			...(webpackConfig.optimization?.splitChunks?.cacheGroups ?? {}),
				vue: {
					test: /[\\/]node_modules[\\/](vue|vue-router|pinia)[\\/]/,
					name: 'vendors-vue',
					priority: 30,
					enforce: true,
				},
			vendors: {
				test: /[\\/]node_modules[\\/]/,
					priority: 10,
				name(module) {
					const context = module.context ?? ''
					if (context.includes(`${path.sep}@tiptap${path.sep}`)) {
						return 'vendors-tiptap'
					}

					if (context.includes(`${path.sep}@nextcloud${path.sep}`)) {
						return 'vendors-nextcloud'
					}

					return 'vendors'
				},
				enforce: true,
			},
		},
	},
}

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