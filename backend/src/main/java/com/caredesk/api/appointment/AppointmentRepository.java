package com.caredesk.api.appointment;

import java.time.LocalDate;
import java.time.LocalTime;
import java.util.List;
import java.util.Optional;

import org.springframework.data.mongodb.repository.MongoRepository;

public interface AppointmentRepository extends MongoRepository<Appointment, String> {
	List<Appointment> findByHospitalIdAndBranchIdAndAppointmentDateOrderByTokenNumber(String hospitalId, String branchId, LocalDate date);
	List<Appointment> findByDoctorProfileIdAndAppointmentDateAndStatusNot(String doctorId, LocalDate date, String status);
	Optional<Appointment> findByHospitalIdAndRequestKey(String hospitalId, String requestKey);
	Optional<Appointment> findByIdAndHospitalIdAndBranchId(String id, String hospitalId, String branchId);
	long countByDoctorProfileIdAndAppointmentDateAndStartsAtAndStatusNot(String doctorId, LocalDate date, LocalTime startsAt, String status);
}
