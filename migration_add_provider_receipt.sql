-- Run this once only if the payments table already exists without provider_receipt.
ALTER TABLE payments ADD COLUMN provider_receipt VARCHAR(100) DEFAULT NULL AFTER provider_reference;
