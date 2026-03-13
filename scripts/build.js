process.env.npm_package_name = process.env.npm_package_name || 'legal_advice'
process.env.npm_package_version = process.env.npm_package_version || '0.1.0'
process.env.NODE_ENV = 'production'

const fs = require('fs')
const path = require('path')
const webpack = require('webpack')
const config = require('../webpack.js')

fs.rmSync(path.resolve(__dirname, '..', 'js'), { recursive: true, force: true })

webpack(config, (error, stats) => {
	if (error) {
		console.error(error)
		process.exit(1)
	}

	const info = stats.toJson({ assets: true, errors: true, warnings: true })
	if (info.warnings?.length) {
		console.warn(JSON.stringify(info.warnings, null, 2))
	}

	if (info.errors?.length) {
		console.error(JSON.stringify(info.errors, null, 2))
		process.exit(1)
	}

	console.log(JSON.stringify(info.assets?.map((asset) => ({ name: asset.name, size: asset.size })), null, 2))
})