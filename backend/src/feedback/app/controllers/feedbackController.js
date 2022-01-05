const { now } = require('mongoose')
const pool = require('../../config/db')
const db = require('../../config/db')


class FeedbackController {

    //ham get trong class FeedbackController

    async get(req, res, next) {
        try {
            const query = "SELECT * FROM feedback ORDER BY id DESC"
            const data = await db.query(query)
            const allUser = data.rows
            res.status(200).json(allUser)
        } catch (error) {
            res.status(400).json({
                success: false,
                error
            })
        }
    }
    //ham post trong class FeedbackController
    async post(req,res,next) {
       
        let read = false;

        //khai bao request.body

        const {userid,title,content,createdat} = req.body;


        // query insert vao table feedback, no nhu sql nen de nho lam
        db.query('INSERT INTO feedback(userid,title,content,isread) VALUES ($1, $2, $3, $4 )', 
        [userid,title,content,read], (err,results) => {
            // chia thanh 2 truong hop loi va khong loi. Neu loi xay ra thi success: fail tren json
            if(err)
            {
                console.log("khong ton tai userId")
                res.status(401).json({
                    success: false,                  
                    err,         
                })

            }
                else res.status(200).json(results.userId)
        })
    }

    async put(req,res,next) {
        const {id} = req.body


        // query insert vao table feedback, no nhu sql nen de nho lam
        db.query('UPDATE feedback SET isread = true WHERE id = $1', 
        [id], (err,results) => {
            // chia thanh 2 truong hop loi va khong loi. Neu loi xay ra thi success: fail tren json
            if(err)
            {
                console.log("khong ton tai id")
                res.status(401).json({
                    success: false,                  
                    err,         
                })

            }
                else res.status(200).json(results.userId)
        })

    }



}


module.exports = new FeedbackController



