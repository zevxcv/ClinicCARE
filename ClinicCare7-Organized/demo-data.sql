-- Import after schema.sql (or upgrade.sql) and administrator setup.
-- Use only in a test database. Re-importing skips an already seeded dataset.
USE cliniccarefive;
START TRANSACTION;
INSERT IGNORE INTO staff(name,username,email,password,role) VALUES('Demo Administrator','admin','admin@cliniccare.test','$2b$12$eKWkSH1pCf40F1XV.LUxQuT3.bH6i61SHIYRgVcSSh7pI1JXHJWkO','Admin');
INSERT IGNORE INTO staff(name,username,email,password,role) VALUES('Demo Clinic Nurse','nurse','nurse@cliniccare.test','$2b$12$wyeeSaB26q3Jb2FC5lSJlu4zQd.Nw2makwY5vaXbf/w4gJdqto9c.','Nurse');

SET @staff = (SELECT id FROM staff WHERE active=1 AND role='Admin' ORDER BY id LIMIT 1);
SET @nurse = (SELECT id FROM staff WHERE username='nurse');
SET @day = DATE(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '+08:00'));
SET @now = CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '+08:00');
SET @month = DATE_FORMAT(@day, '%Y-%m-01');

INSERT IGNORE INTO students(number,name,grade,section,guardian,phone,allergies,conditions)
SELECT 'DEMO-001','Avery Demo','10','A','Demo Guardian A','','Peanuts (fictional)',''
WHERE @staff IS NOT NULL;
SET @seeded = ROW_COUNT();
SET @first = (SELECT id FROM students WHERE number='DEMO-001' AND @seeded=1);

INSERT INTO students(number,name,grade,section,guardian,phone,allergies,conditions)
SELECT 'DEMO-002','Riley Demo','10','A','Demo Guardian B','','','Asthma (fictional)' WHERE @seeded=1
UNION ALL SELECT 'DEMO-003','Jordan Demo','10','A','Demo Guardian C','','','' WHERE @seeded=1
UNION ALL SELECT 'DEMO-004','Casey Demo','9','B','Demo Guardian D','','','' WHERE @seeded=1;
SET @second = (SELECT id FROM students WHERE number='DEMO-002' AND @seeded=1);
SET @third = (SELECT id FROM students WHERE number='DEMO-003' AND @seeded=1);
SET @fourth = (SELECT id FROM students WHERE number='DEMO-004' AND @seeded=1);

INSERT INTO growth(student_id,date,height,weight)
SELECT @first,DATE_SUB(@day,INTERVAL 30 DAY),155,48 WHERE @seeded=1
UNION ALL SELECT @first,@day,156,49 WHERE @seeded=1;
INSERT INTO immunizations(student_id,vaccine,date,status)
SELECT @first,'Demo vaccine entry',@day,'Planned' WHERE @seeded=1;

INSERT INTO visits(student_id,staff_id,created,complaint,assessment,disposition)
SELECT @first,COALESCE(@nurse,@staff),DATE_SUB(@now,INTERVAL 3 HOUR),'Demo dizziness','Fictional observation case','Observation' WHERE @seeded=1;
SET @observation = IF(ROW_COUNT()=1,LAST_INSERT_ID(),NULL);
INSERT INTO visits(student_id,staff_id,created,complaint,assessment,disposition)
SELECT @second,@staff,@now,'Demo headache','Fictional follow-up case','Requires Follow-up' WHERE @seeded=1;
SET @follow = IF(ROW_COUNT()=1,LAST_INSERT_ID(),NULL);
INSERT INTO visits(student_id,staff_id,created,complaint,disposition)
SELECT @third,@staff,@now,'Demo minor scrape','Returned to Class' WHERE @seeded=1;
SET @certificate = IF(ROW_COUNT()=1,LAST_INSERT_ID(),NULL);
INSERT INTO visits(student_id,staff_id,created,complaint,disposition)
SELECT @fourth,@staff,DATE_SUB(@month,INTERVAL 3 DAY),'Demo stomachache','Sent Home' WHERE @seeded=1
UNION ALL SELECT @third,@staff,DATE_SUB(@month,INTERVAL 5 DAY),'Demo headache','Returned to Class' WHERE @seeded=1;
INSERT INTO issued_documents(visit_id,kind,staff_id,created)
SELECT @certificate,'excuse',@staff,@now WHERE @seeded=1;
INSERT INTO incidents(visit_id,student_id,staff_id,occurred,location,narrative,guardian_status)
SELECT @observation,@first,@staff,DATE_SUB(@now,INTERVAL 3 HOUR),'Demo school courtyard','Fictional incident for testing the critical alert.','Pending' WHERE @seeded=1;

UPDATE beds SET status='Occupied',visit_id=@observation,assigned=DATE_SUB(@now,INTERVAL 3 HOUR) WHERE id=1 AND @seeded=1 AND status='Available';
UPDATE beds SET status='Needs Cleaning' WHERE id=2 AND @seeded=1 AND status='Available';
SET @bed = (SELECT id FROM beds WHERE id=3 AND @seeded=1);

INSERT INTO medicines(code,name,unit,`reorder`,category,strength,`form`)
SELECT 'DEMO-MED-01','Paracetamol (Demo)','tablet',20,'Pain relief','500 mg','Tablet' WHERE @seeded=1
UNION ALL SELECT 'DEMO-MED-02','Saline (Demo)','bottle',5,'First aid','0.9%','Solution' WHERE @seeded=1
UNION ALL SELECT 'DEMO-MED-03','Bandage (Demo)','roll',10,'First aid','','Roll' WHERE @seeded=1;
SET @low = (SELECT id FROM medicines WHERE code='DEMO-MED-01' AND @seeded=1);
SET @expiry = (SELECT id FROM medicines WHERE code='DEMO-MED-02' AND @seeded=1);
SET @adequate = (SELECT id FROM medicines WHERE code='DEMO-MED-03' AND @seeded=1);
INSERT INTO batches(medicine_id,lot,expiry,qty,cost)
SELECT @low,'DEMO-LOT-A',DATE_ADD(@day,INTERVAL 180 DAY),6,3.50 WHERE @seeded=1
UNION ALL SELECT @expiry,'DEMO-LOT-B',DATE_ADD(@day,INTERVAL 14 DAY),18,32.00 WHERE @seeded=1
UNION ALL SELECT @adequate,'DEMO-LOT-C',DATE_ADD(@day,INTERVAL 300 DAY),45,15.00 WHERE @seeded=1
UNION ALL SELECT @low,'DEMO-LOT-D',DATE_SUB(@day,INTERVAL 7 DAY),3,3.50 WHERE @seeded=1;
INSERT INTO movements(batch_id,staff_id,kind,qty,created)
SELECT id,@staff,'Receipt',qty,@now FROM batches
WHERE @seeded=1 AND medicine_id IN (@low,@expiry,@adequate);

INSERT INTO events(title,starts,ends,kind,student_id,source_visit)
SELECT 'Demo follow-up appointment',TIMESTAMP(@day,'15:00:00'),TIMESTAMP(@day,'15:30:00'),'Follow-up',@second,@follow WHERE @seeded=1
UNION ALL SELECT 'Demo missed follow-up',DATE_SUB(@now,INTERVAL 1 HOUR),DATE_SUB(@now,INTERVAL 30 MINUTE),'Follow-up',@fourth,NULL WHERE @seeded=1;
INSERT INTO events(title,starts,ends,kind,student_id,repeat_until)
SELECT 'Demo daily medication reminder',TIMESTAMP(@day,'09:00:00'),TIMESTAMP(@day,'09:15:00'),'Routine care',@second,DATE_ADD(@day,INTERVAL 7 DAY) WHERE @seeded=1;
INSERT INTO events(title,starts,ends,kind,grade,section)
SELECT 'Demo Grade 10 vision screening',TIMESTAMP(@day,'11:00:00'),TIMESTAMP(@day,'12:00:00'),'Mass health event','10','A' WHERE @seeded=1;
SET @cohort = IF(ROW_COUNT()=1,LAST_INSERT_ID(),NULL);
INSERT INTO event_checks(event_id,student_id,day,status)
SELECT @cohort,@first,@day,'Checked' WHERE @seeded=1
UNION ALL SELECT @cohort,@third,@day,'Requires Individual Follow-up' WHERE @seeded=1;
INSERT INTO events(title,starts,ends,kind,bed_id)
SELECT 'Demo bed reservation',TIMESTAMP(@day,'13:00:00'),TIMESTAMP(@day,'14:30:00'),'Clinic event',@bed WHERE @seeded=1;
INSERT INTO events(title,starts,ends,kind)
SELECT 'Demo reduced staffing',TIMESTAMP(DATE_ADD(@day,INTERVAL 1 DAY),'07:00:00'),
       TIMESTAMP(DATE_ADD(@day,INTERVAL 1 DAY),'17:00:00'),'Reduced staffing' WHERE @seeded=1;

INSERT INTO events(title,starts,kind)
SELECT 'Demo approved supply delivery',TIMESTAMP(DATE_ADD(@day,INTERVAL 2 DAY),'08:00:00'),'Inventory delivery' WHERE @seeded=1;
SET @delivery = IF(ROW_COUNT()=1,LAST_INSERT_ID(),NULL);
INSERT INTO purchase_requests(medicine_id,staff_id,qty,approved_at,delivery,event_id)
SELECT @adequate,@staff,25,@now,DATE_ADD(@day,INTERVAL 2 DAY),@delivery WHERE @seeded=1;

INSERT INTO events(title,starts,ends,kind)
SELECT 'Check clinic supplies (Demo)',TIMESTAMP(@day,'13:00:00'),TIMESTAMP(@day,'13:30:00'),'Task' WHERE @seeded=1
UNION ALL SELECT 'Prepare weekly summary (Demo)',TIMESTAMP(@day,'16:00:00'),TIMESTAMP(@day,'16:15:00'),'Reminder' WHERE @seeded=1;
INSERT INTO medicines(code,name,unit,`reorder`,category,strength,`form`)
SELECT 'DEMO-MED-04','Antiseptic (Demo)','bottle',5,'First aid','','Solution' WHERE @seeded=1;
COMMIT;
SELECT CASE WHEN @staff IS NULL THEN 'Set up and activate an administrator, then import again.'
            WHEN @seeded=1 THEN 'Demo data added. Open ClinicCare Overview to generate the alerts.'
            ELSE 'Demo data already exists. No records added.' END AS result;
