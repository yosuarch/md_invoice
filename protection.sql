
-- CodeIgniter v4.7.6 Command Line Tool - Server Time: 2026-11-23 13:09:09 UTC+08:00

-- de'makmur invoicing: protection triggers
-- Run as a user with the TRIGGER privilege on the application database.
DROP TRIGGER IF EXISTS `trg_audit_log_no_update`;
DELIMITER //
CREATE TRIGGER `trg_audit_log_no_update` BEFORE UPDATE ON `audit_log` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is append-only'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_audit_log_no_delete`;
DELIMITER //
CREATE TRIGGER `trg_audit_log_no_delete` BEFORE DELETE ON `audit_log` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is append-only'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_psh_no_update`;
DELIMITER //
CREATE TRIGGER `trg_psh_no_update` BEFORE UPDATE ON `proposal_status_history` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'proposal_status_history is append-only'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_psh_no_delete`;
DELIMITER //
CREATE TRIGGER `trg_psh_no_delete` BEFORE DELETE ON `proposal_status_history` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'proposal_status_history is append-only'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_payment_no_delete`;
DELIMITER //
CREATE TRIGGER `trg_payment_no_delete` BEFORE DELETE ON `payment` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments are never deleted (reverse them)'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_payment_fixed`;
DELIMITER //
CREATE TRIGGER `trg_payment_fixed` BEFORE UPDATE ON `payment` FOR EACH ROW BEGIN IF NEW.amount <> OLD.amount OR NEW.invoice_id <> OLD.invoice_id OR NEW.payment_date <> OLD.payment_date OR NEW.deleted_at IS NOT NULL OR (OLD.reversed_at IS NOT NULL AND NOT (NEW.reversed_at <=> OLD.reversed_at)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payment amounts cannot be changed (reverse the payment)'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_pw_no_delete`;
DELIMITER //
CREATE TRIGGER `trg_pw_no_delete` BEFORE DELETE ON `payment_withholding` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'withheld taxes are never deleted'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_pw_fixed`;
DELIMITER //
CREATE TRIGGER `trg_pw_fixed` BEFORE UPDATE ON `payment_withholding` FOR EACH ROW BEGIN IF NEW.amount <> OLD.amount OR NEW.payment_id <> OLD.payment_id OR NEW.tax_kind <> OLD.tax_kind OR NEW.deleted_at IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'withheld tax amounts cannot be changed'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_refund_no_delete`;
DELIMITER //
CREATE TRIGGER `trg_refund_no_delete` BEFORE DELETE ON `refund` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refunds are never deleted (reverse them)'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_refund_fixed`;
DELIMITER //
CREATE TRIGGER `trg_refund_fixed` BEFORE UPDATE ON `refund` FOR EACH ROW BEGIN IF NEW.amount <> OLD.amount OR NEW.invoice_id <> OLD.invoice_id OR NEW.refund_date <> OLD.refund_date OR NEW.deleted_at IS NOT NULL OR (OLD.reversed_at IS NOT NULL AND NOT (NEW.reversed_at <=> OLD.reversed_at)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refund amounts cannot be changed (reverse the refund)'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_cn_no_delete`;
DELIMITER //
CREATE TRIGGER `trg_cn_no_delete` BEFORE DELETE ON `credit_note` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'credit notes are never deleted'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_cn_fixed`;
DELIMITER //
CREATE TRIGGER `trg_cn_fixed` BEFORE UPDATE ON `credit_note` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'credit notes cannot be changed'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_cni_no_delete`;
DELIMITER //
CREATE TRIGGER `trg_cni_no_delete` BEFORE DELETE ON `credit_note_item` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'credit note lines are never deleted'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_cni_fixed`;
DELIMITER //
CREATE TRIGGER `trg_cni_fixed` BEFORE UPDATE ON `credit_note_item` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'credit note lines cannot be changed'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_iw_no_delete`;
DELIMITER //
CREATE TRIGGER `trg_iw_no_delete` BEFORE DELETE ON `invoice_withholding` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'expected withholding is never deleted'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_iw_fixed`;
DELIMITER //
CREATE TRIGGER `trg_iw_fixed` BEFORE UPDATE ON `invoice_withholding` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'expected withholding cannot be changed'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_invoice_no_delete`;
DELIMITER //
CREATE TRIGGER `trg_invoice_no_delete` BEFORE DELETE ON `invoice` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invoices are never deleted'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_invoice_issued`;
DELIMITER //
CREATE TRIGGER `trg_invoice_issued` BEFORE UPDATE ON `invoice` FOR EACH ROW BEGIN IF OLD.issued_at IS NOT NULL AND (NEW.deleted_at IS NOT NULL OR NEW.invoice_number <> OLD.invoice_number OR NEW.grand_total <> OLD.grand_total OR NEW.customer_id <> OLD.customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'an issued invoice cannot be deleted or changed (use a credit note or void)'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_invoice_item_no_delete`;
DELIMITER //
CREATE TRIGGER `trg_invoice_item_no_delete` BEFORE DELETE ON `invoice_item` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invoice lines are never hard-deleted'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_invoice_item_issued`;
DELIMITER //
CREATE TRIGGER `trg_invoice_item_issued` BEFORE UPDATE ON `invoice_item` FOR EACH ROW BEGIN IF (SELECT i.issued_at FROM invoice i WHERE i.id = OLD.invoice_id) IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'lines of an issued invoice cannot be changed'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_dn_no_delete`;
DELIMITER //
CREATE TRIGGER `trg_dn_no_delete` BEFORE DELETE ON `delivery_note` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'delivery notes are never deleted (cancel them)'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_dn_fixed`;
DELIMITER //
CREATE TRIGGER `trg_dn_fixed` BEFORE UPDATE ON `delivery_note` FOR EACH ROW BEGIN IF NEW.delivery_number <> OLD.delivery_number OR NEW.invoice_id <> OLD.invoice_id OR NEW.deleted_at IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'delivery note number and invoice cannot be changed'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_dni_no_delete`;
DELIMITER //
CREATE TRIGGER `trg_dni_no_delete` BEFORE DELETE ON `delivery_note_item` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'delivery note lines are never deleted'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_dni_fixed`;
DELIMITER //
CREATE TRIGGER `trg_dni_fixed` BEFORE UPDATE ON `delivery_note_item` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'delivery note lines cannot be changed'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_dne_no_update`;
DELIMITER //
CREATE TRIGGER `trg_dne_no_update` BEFORE UPDATE ON `delivery_note_event` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'the delivery tracking log is append-only'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_dne_no_delete`;
DELIMITER //
CREATE TRIGGER `trg_dne_no_delete` BEFORE DELETE ON `delivery_note_event` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'the delivery tracking log is append-only'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_prev_no_update`;
DELIMITER //
CREATE TRIGGER `trg_prev_no_update` BEFORE UPDATE ON `proposal_revision` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'proposal revisions are append-only'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_prev_no_delete`;
DELIMITER //
CREATE TRIGGER `trg_prev_no_delete` BEFORE DELETE ON `proposal_revision` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'proposal revisions are append-only'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_att_no_delete`;
DELIMITER //
CREATE TRIGGER `trg_att_no_delete` BEFORE DELETE ON `attachment` FOR EACH ROW BEGIN IF TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'attachments are never deleted (they are removed with a soft delete)'; END IF; END //
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_att_fixed`;
DELIMITER //
CREATE TRIGGER `trg_att_fixed` BEFORE UPDATE ON `attachment` FOR EACH ROW BEGIN IF NEW.file_path <> OLD.file_path OR NEW.entity_type <> OLD.entity_type OR NEW.entity_id <> OLD.entity_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'the file and document of an attachment cannot be changed'; END IF; END //
DELIMITER ;
