SELECT a.*
FROM parking_lots a
         JOIN csv_imports b on b.id = a.csv_import_id
WHERE b.status = true;