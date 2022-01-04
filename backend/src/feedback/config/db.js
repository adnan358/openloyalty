const Pool = require("pg").Pool;
//ket noi databse postgresql
const pool = new Pool({
    user: 'openloyalty',
    password: 'openloyalty',
    database: 'openloyalty',
    host:'openloyalty.localhost',
    port:5432,
})

module.exports = pool;