const { response } = require('express');
const express = require('express');
const route = require('.');
const router = express.Router();
const feedbackController = require('../app/controllers/feedbackController')
const db = require ('../config/db')



router.post('/',feedbackController.post)


router.get('/', feedbackController.get)

router.put('/',feedbackController.put)

module.exports = router