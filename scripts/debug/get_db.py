import sqlite3
import json

db = sqlite3.connect('hospital_portal.db')
c = db.cursor()
c.execute("SELECT * FROM upi_accounts")
rows = c.fetchall()
columns = [desc[0] for desc in c.description]
data = [dict(zip(columns, row)) for row in rows]
print(json.dumps(data, indent=2))
