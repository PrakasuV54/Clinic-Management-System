const sqlite3 = require('sqlite3').verbose();
const path = require('path');

const dbPath = path.resolve(__dirname, '../database/sqlite/hospital_portal.db');
const db = new sqlite3.Database(dbPath, (err) => {
    if (err) {
        console.error('Error connecting to DB:', err);
        process.exit(1);
    }
});

db.all("SELECT username, password FROM users", [], (err, rows) => {
    if (err) {
        console.error('Error querying users:', err);
        process.exit(1);
    }
    
    let plaintextCount = 0;
    const plaintextUsers = [];
    
    rows.forEach(row => {
        // password_hash BCrypt hashes start with $2y$ (or $2a$ / $2x$)
        const isHashed = row.password.startsWith('$2y$') || row.password.startsWith('$2a$');
        if (!isHashed) {
            plaintextCount++;
            plaintextUsers.push(row.username);
        }
    });
    
    console.log(JSON.stringify({
        total: rows.length,
        plaintextCount: plaintextCount,
        plaintextUsers: plaintextUsers
    }));
    db.close();
});
