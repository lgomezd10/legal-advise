import webpackConfig from '../../webpack'

describe('webpack config', () => {
	it('keeps the main entrypoint self-contained for Nextcloud', () => {
		expect(webpackConfig.entry).toMatchObject({
			main: expect.stringContaining('src'),
		})

		expect(webpackConfig.output).toMatchObject({
			publicPath: 'auto',
			filename: '[name].js',
			chunkFilename: '[name].js',
		})

		expect(webpackConfig.optimization?.splitChunks).toMatchObject({
			chunks: 'async',
		})
	})
})