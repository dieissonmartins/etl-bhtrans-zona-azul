create table parking_lots
(
    id                       int auto_increment,
    parking_id               int                                                                           null,
    vacancies_physical_count int                                                                           null,
    vacancies_rotating_count int                                                                           null,
    time_permanence_label    varchar(255)                                                                  null,
    time_permanence          time                                                                          null,
    public_place             varchar(500)                                                                  null,
    reference                varchar(500)                                                                  null,
    neighborhood             varchar(150)                                                                  null,
    period_label             varchar(100)                                                                  null,
    time_period_start        time                                                                          null,
    time_period_end          time                                                                          null,
    day_label                varchar(100)                                                                  null,
    day                      enum ('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') null,
    polygon                  varchar(250)                                                                  null,
    constraint parking_lots_pk
        primary key (id)
);