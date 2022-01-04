
const feedbackRoute = require('./feedbackRoute')

function route(app) {
    app.use('/feedback',feedbackRoute )

    app.get('/', (req, res) => {
        res.send('index')
    })

    
}


module.exports = route
