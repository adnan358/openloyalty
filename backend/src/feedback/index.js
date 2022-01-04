const express = require('express')
const cors = require('cors')
const route = require('./routes/index');
const app = express();
const port = 6000
const feedbackRoute = require('./routes/feedbackRoute')


app.use(express.urlencoded({
    extended: true
}))

app.use(express.json())


// read api
app.use(cors())


// navigattion


app.use('/feedback',feedbackRoute )

app.get('/', (req, res) => {
    res.send('index')
})

app.post('/', (req,res) =>{
    console.log(res.body);
})





// start server
app.listen(port, () =>{
    console.log(`server has started on port ${port}`)
})
