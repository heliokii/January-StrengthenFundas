using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;

namespace Solution
{
    public class Solution
    {
        public static Dictionary<string, int> AverageAgeForEachCompany(List<Employee> employees)
        {
            return employees
                .GroupBy(e => e.Company)
                .OrderBy(g => g.Key)
                .ToDictionary(
                    g => g.Key,
                    g => (int)Math.Round(g.Average(e => e.Age))
                );
        }
        
        public static Dictionary<string, int> CountOfEmployeesForEachCompany(List<Employee> employees)
        {
            return employees
                .GroupBy(e => e.Company)
                .OrderBy(g => g.Key)
                .ToDictionary(
                    g => g.Key,
                    g => g.Count()
                );
        }
        
        public static Dictionary<string, Employee> OldestAgeForEachCompany(List<Employee> employees)
        {
            return employees
                .GroupBy(e => e.Company)
                .OrderBy(g => g.Key)
                .ToDictionary(
                    g => g.Key,
                    g => g.OrderByDescending(e => e.Age).First()
                );
        }
    }
    
    public class Employee
    {
        public string FirstName { get; set; }
        public string LastName { get; set; }
        public int Age { get; set; }
        public string Company { get; set; }
    }

    public class Program
    {
        public static void Main()
        {   
            int countOfEmployees = int.Parse(Console.ReadLine());
            
            var employees = new List<Employee>();
            
            for (int i = 0; i < countOfEmployees; i++)
            {
                string str = Console.ReadLine();
                string[] strArr = str.Split(' ');
                employees.Add(new Employee { 
                    FirstName = strArr[0], 
                    LastName = strArr[1], 
                    Company = strArr[2], 
                    Age = int.Parse(strArr[3]) 
                });
            }
            
            foreach (var emp in Solution.AverageAgeForEachCompany(employees))
            {
                Console.WriteLine($"The average age for company {emp.Key} is {emp.Value}");
            }
            
            foreach (var emp in Solution.CountOfEmployeesForEachCompany(employees))
            {
                Console.WriteLine($"The count of employees for company {emp.Key} is {emp.Value}");
            }
            
            foreach (var emp in Solution.OldestAgeForEachCompany(employees))
            {
                Console.WriteLine($"The oldest employee of company {emp.Key} is {emp.Value.FirstName} {emp.Value.LastName} having age {emp.Value.Age}");
            }
        }
    }
}