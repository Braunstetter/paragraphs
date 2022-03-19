const postcssConfig = {
    plugins: [
        require('autoprefixer'),
        require('postcss-simple-vars')({variables: require('./variables')}),
        require('postcss-import'),
        require('postcss-nested-ancestors'),
        require('postcss-nested'),
    ]
};

if (process.env.NODE_ENV === 'production') {
    postcssConfig.plugins.push(
        require('cssnano')({preset: 'default'})
    );
}

module.exports = postcssConfig;