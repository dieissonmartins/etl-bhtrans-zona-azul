CREATE TABLE csv_imports
(
    id        INT AUTO_INCREMENT,
    status    BOOLEAN DEFAULT true NOT NULL,
    date      DATE                 NULL,
    file_name varchar(500)         null,
    path      VARCHAR(500)         NULL,
    CONSTRAINT csv_imports_pk PRIMARY KEY (id),
    INDEX status_index (status),
    INDEX date_index (date)
);